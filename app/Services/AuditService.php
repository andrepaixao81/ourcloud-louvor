<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    private const SENSITIVE = ['password','remember_token','access_token_encrypted','refresh_token_encrypted','token','secret','client_secret'];

    public function modelEvent(string $event, Model $model, array $old = [], array $new = [], ?string $reason = null, array $metadata = []): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('audit_logs')) return;
        $actor = $this->actorUser();
        $effective = Auth::user();
        $orgId = $model->getAttribute('organization_id') ?: $actor?->organization_id ?: $effective?->organization_id;
        AuditLog::query()->create([
            'organization_id' => $orgId,
            'user_id' => $actor?->id,
            'event' => class_basename($model).'.'.$event,
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'old_values' => $this->sanitize($old),
            'new_values' => $this->sanitize($new),
            'reason' => $reason,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'metadata' => $this->sanitize($this->withAssistance($metadata)),
            'created_at' => now(),
        ]);
    }

    public function event(string $event, ?Model $model = null, ?string $reason = null, array $metadata = []): void
    {
        $actor = $this->actorUser();
        $effective = Auth::user();
        AuditLog::query()->create([
            'organization_id' => $model?->getAttribute('organization_id') ?: $actor?->organization_id ?: $effective?->organization_id,
            'user_id' => $actor?->id,
            'event' => $event,
            'auditable_type' => $model ? $model::class : null,
            'auditable_id' => $model?->getKey(),
            'reason' => $reason,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'metadata' => $this->sanitize($this->withAssistance($metadata)),
            'created_at' => now(),
        ]);
    }

    private function actorUser(): ?User
    {
        try {
            if (app()->bound('request')) {
                $actor = request()->attributes->get(MinisterAssistanceContext::ACTOR_ATTRIBUTE);
                if ($actor instanceof User) return $actor;
            }
        } catch (\Throwable) {
            // Sem contexto HTTP.
        }
        return Auth::user();
    }

    private function withAssistance(array $metadata): array
    {
        try {
            if (! app()->bound('request')) return $metadata;
            $actor = request()->attributes->get(MinisterAssistanceContext::ACTOR_ATTRIBUTE);
            $person = request()->attributes->get(MinisterAssistanceContext::PERSON_ATTRIBUTE);
            $effective = request()->attributes->get(MinisterAssistanceContext::EFFECTIVE_USER_ATTRIBUTE);

            if ($actor instanceof User && $person instanceof \App\Models\Person && $effective instanceof User) {
                $metadata['assisted_person_id'] = $person->id;
                $metadata['assisted_person_name'] = $person->display_name;
                $metadata['assisted_user_id'] = $effective->id;
                $metadata['assisted_user_name'] = $effective->name;
                $metadata['minister_assistance_acting_on_behalf'] = true;
                $metadata['actor_user_id'] = $actor->id;
                $metadata['actor_user_name'] = $actor->name;
                $metadata['administrator_acting_on_behalf'] = $actor->isAdministrator();
                $metadata['ministry_manager_acting_on_behalf'] = $actor->isMinistryManager();
            }
        } catch (\Throwable) {
            // Auditoria nunca deve quebrar a operação.
        }
        return $metadata;
    }

    private function sanitize(array $values): array
    {
        foreach ($values as $key => $value) {
            foreach (self::SENSITIVE as $needle) {
                if (str_contains(strtolower((string) $key), $needle)) {
                    $values[$key] = '[REDACTED]';
                    continue 2;
                }
            }
        }
        return $values;
    }
}
