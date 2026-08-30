<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasUuids, Notifiable, SoftDeletes;

    protected $fillable = ['organization_id', 'name', 'email', 'password', 'active', 'must_change_password', 'calendar_feed_token'];
    protected $hidden = ['password', 'remember_token', 'calendar_feed_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    public function isAdministrator(): bool { return $this->roles()->where('slug', 'administrator')->exists(); }

    public function usesMinisterOnlyNavigation(): bool
    {
        $slugs = $this->roles()->pluck('slug');
        return $slugs->contains('minister')
            && $slugs->intersect(['administrator', 'ministry_manager', 'leadership'])->isEmpty();
    }

    public function hasActiveMinistryRole(): bool
    {
        if ($this->relationLoaded('person') && $this->person?->relationLoaded('ministryRoles')) {
            return $this->person->ministryRoles->contains(fn ($role) => (bool) $role->active);
        }

        return $this->person()
            ->whereHas('ministryRoles', fn ($query) => $query->where('active', true))
            ->exists();
    }
    public function isMinistryManager(): bool { return $this->roles()->where('slug', 'ministry_manager')->exists(); }
    public function canAssistMinisters(): bool { return $this->isAdministrator() || $this->isMinistryManager(); }

    public function isPastor(): bool
    {
        return $this->person()
            ->whereHas('ministryRoles', fn ($query) => $query->where('active', true)->where(function ($q) {
                $q->where('slug', 'pastor')->orWhereRaw('LOWER(name) = ?', ['pastor']);
            }))
            ->exists();
    }

    public function canViewBirthdays(): bool
    {
        if (! $this->active) return false;
        if ($this->isPastor()) return true;

        // RC87: somente o Membro comum fica sem acesso aos aniversariantes.
        // Perfis operacionais do louvor (Ministro/Participante), liderança,
        // Responsável pelo Ministério e Administrador continuam autorizados.
        $slugs = $this->roles()->pluck('slug');
        if ($slugs->isEmpty()) return false;

        return ! $this->isMemberWithoutMinistryAccess();
    }

    public function canManageBirthdaySettings(): bool
    {
        if ($this->isMemberWithoutMinistryAccess()) return false;
        return $this->isAdministrator() || $this->isMinistryManager() || $this->hasPermission('birthdays.manage');
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function roles(): BelongsToMany { return $this->belongsToMany(Role::class); }
    public function person() { return $this->hasOne(Person::class); }

    /**
     * Membro puro é propositalmente um perfil de conta, sem módulos operacionais.
     * Para participar do louvor, a conta deve receber Participante do Louvor,
     * Ministro ou outro papel operacional. ACL individual continua podendo
     * liberar ou negar capacidades específicas, inclusive para Membro.
     */
    public function isBasicMemberOnly(): bool
    {
        $slugs = $this->roles()->pluck('slug');
        return $slugs->count() === 1 && $slugs->first() === 'member';
    }



    public function isMemberWithoutMinistryAccess(): bool
    {
        $slugs = $this->roles()->pluck('slug');
        return $slugs->contains('member')
            && $slugs->intersect(['administrator', 'ministry_manager', 'minister', 'participant', 'leadership'])->isEmpty();
    }

    public function isParticipantOnly(): bool
    {
        $slugs = $this->roles()->pluck('slug');
        return $slugs->contains('participant')
            && ! $slugs->intersect(['administrator', 'ministry_manager', 'minister', 'leadership'])->isNotEmpty();
    }

    public function canSeeSpecialEvents(): bool
    {
        return ! $this->isMemberWithoutMinistryAccess() && $this->hasPermission('special_events.view');
    }

    public function hasPermission(string $permission): bool
    {
        if (! $this->active) return false;

        // RC27: ACL individual deve prevalecer inclusive para o papel Membro.
        // O papel Membro continua sem permissões por padrão, mas uma exceção
        // explícita PERMITIR/NEGAR precisa ser respeitada pelo motor de ACL.
        $permissionModel = Permission::query()->withoutGlobalScopes()->where('name', $permission)->first();
        if (! $permissionModel) return false;

        $override = UserPermissionOverride::query()->withoutGlobalScopes()
            ->where('user_id', $this->id)
            ->where('permission_id', $permissionModel->id)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();

        if ($override) return (bool) $override->allowed;

        return $this->roles()
            ->whereHas('permissions', fn ($q) => $q->where('permissions.id', $permissionModel->id))
            ->exists();
    }

    public function preferredHomeRouteName(): string
    {
        if ($this->usesMinisterOnlyNavigation() && $this->hasPermission('agenda.view')) return 'my.agenda';
        if ($this->hasPermission('reports.dashboard')) return 'dashboard';
        if ($this->hasPermission('agenda.view') && ! $this->isParticipantOnly() && ! $this->isMemberWithoutMinistryAccess()) return 'my.agenda';
        if ($this->hasPermission('calendar.view')) return 'calendar.index';
        if ($this->hasPermission('services.view')) return 'services.index';
        if ($this->hasPermission('songs.view')) return 'songs.index';
        return 'account.show';
    }

    public function ensureCalendarFeedToken(): string
    {
        if (!$this->calendar_feed_token) {
            $this->forceFill(['calendar_feed_token' => Str::random(80)])->saveQuietly();
        }
        return $this->calendar_feed_token;
    }
}
