<?php

namespace App\Services;

use App\Jobs\SendWhatsappNotificationJob;
use App\Models\User;
use App\Models\WhatsappDeliveryLog;
use App\Models\WhatsappSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TeamNotificationService
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    /** @return Collection<int,User> */
    public function operationalUsers(string $organizationId): Collection
    {
        return User::query()
            ->with(['person.ministryRoles', 'roles'])
            ->where('organization_id', $organizationId)
            ->where('active', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('slug', [
                'administrator', 'ministry_manager', 'minister', 'participant', 'leadership',
            ]))
            ->get()
            ->reject(fn (User $user) => $user->isMemberWithoutMinistryAccess())
            ->values();
    }

    /**
     * Cria aviso interno e, quando configurado, enfileira template WhatsApp para
     * cada usuário operacional com telefone cadastrado.
     */
    public function announce(
        string $organizationId,
        string $title,
        string $summary,
        string $actionUrl,
        string $eventKey,
        string $priority = 'normal',
        bool $sendWhatsapp = true,
    ): array {
        return $this->announceToUsers(
            $organizationId,
            $this->operationalUsers($organizationId),
            $title,
            $summary,
            $actionUrl,
            $eventKey,
            $priority,
            $sendWhatsapp,
        );
    }

    /**
     * RC79: compromissos comuns da Agenda seguem a própria ACL do calendário.
     * Assim um usuário que recebeu calendar.view individualmente também recebe
     * os avisos dessa agenda, sem precisar ganhar um papel ministerial.
     */
    public function announceCalendar(
        string $organizationId,
        string $title,
        string $summary,
        string $actionUrl,
        string $eventKey,
        string $priority = 'normal',
        bool $sendWhatsapp = true,
    ): array {
        $users = User::query()
            ->with(['person.ministryRoles', 'roles'])
            ->where('organization_id', $organizationId)
            ->where('active', true)
            ->get()
            ->filter(fn (User $user) => $user->hasPermission('calendar.view') || $user->hasActiveMinistryRole())
            ->values();

        return $this->announceToUsers(
            $organizationId,
            $users,
            $title,
            $summary,
            $actionUrl,
            $eventKey,
            $priority,
            $sendWhatsapp,
        );
    }

    private function announceToUsers(
        string $organizationId,
        Collection $users,
        string $title,
        string $summary,
        string $actionUrl,
        string $eventKey,
        string $priority,
        bool $sendWhatsapp,
    ): array {
        $settings = WhatsappSetting::query()->where('organization_id', $organizationId)->first();
        $queued = 0;
        $internal = 0;

        foreach ($users as $user) {
            try {
                $this->notifications->create(
                    $user,
                    $title,
                    $summary,
                    'information',
                    $priority,
                    $actionUrl,
                    null,
                    ['event_key' => $eventKey]
                );
                $internal++;
            } catch (\Throwable $exception) {
                Log::warning('Falha ao criar aviso interno do SGML.', ['user_id' => $user->id, 'error' => $exception->getMessage()]);
            }

            if (! $sendWhatsapp || ! $settings?->isConfigured()) {
                continue;
            }

            $phone = $this->normalizePhone((string) ($user->person?->phone ?? ''));
            if ($phone === null) continue;

            $log = WhatsappDeliveryLog::query()->create([
                'organization_id' => $organizationId,
                'user_id' => $user->id,
                'phone' => $phone,
                'provider' => $settings->selectedProvider(),
                'event_key' => Str::limit($eventKey, 100, ''),
                'title' => Str::limit($title, 180, ''),
                'summary' => $summary,
                'action_url' => $actionUrl,
                'status' => 'queued',
            ]);

            SendWhatsappNotificationJob::dispatch($log->id)->afterCommit();
            $queued++;
        }

        return ['internal' => $internal, 'whatsapp_queued' => $queued, 'recipients' => $users->count()];
    }

    public function normalizePhone(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?: '';
        if ($digits === '') return null;

        // Instalação brasileira: números locais de 10/11 dígitos recebem DDI 55.
        // Números já em E.164 (somente dígitos) são preservados.
        if (in_array(strlen($digits), [10, 11], true)) {
            $digits = '55'.$digits;
        }

        if (strlen($digits) < 12 || strlen($digits) > 15) return null;
        return $digits;
    }
}
