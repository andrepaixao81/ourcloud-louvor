<?php

namespace App\Services;

use App\Models\ActionItem;
use App\Models\Service;
use App\Models\SpecialEvent;
use App\Models\WhatsappDeliveryLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OperationalCleanupService
{
    public function __construct(
        private readonly InternalCalendarService $calendar,
        private readonly RecurringServiceGeneratorService $recurring,
    ) {}

    /**
     * Dados removidos por esta rotina são apenas dados operacionais derivados da
     * escala/testes. Cadastros, biblioteca, ensaios e regras dos cultos permanecem.
     */
    public function preview(string $organizationId): array
    {
        return [
            'recurring_services' => DB::table('services')->where('organization_id', $organizationId)
                ->where(fn ($q) => $q->where('origin', 'recurrence')->orWhereNotNull('recurring_rule_id'))->count(),
            'manual_services' => DB::table('services')->where('organization_id', $organizationId)
                ->whereNull('recurring_rule_id')->whereNull('special_event_id')->where('origin', '!=', 'recurrence')->count(),
            'special_events' => DB::table('special_events')->where('organization_id', $organizationId)->count(),
            'special_event_responses' => DB::table('special_event_responses')->where('organization_id', $organizationId)->count(),
            'special_event_actions' => $this->specialEventActionItems($organizationId)->count(),
            'assignments' => DB::table('schedule_assignments')->where('organization_id', $organizationId)->count(),
            'confirmations' => DB::table('schedule_confirmations')->where('organization_id', $organizationId)->count(),
            'swaps' => DB::table('swap_requests')->where('organization_id', $organizationId)->count(),
            'additional_ministers' => DB::table('service_additional_ministers')->where('organization_id', $organizationId)->count(),
            'repertoires' => DB::table('repertoires')->where('organization_id', $organizationId)->count(),
            'executions' => DB::table('service_executions')->where('organization_id', $organizationId)->count(),
            'remembered_keys' => DB::table('minister_song_keys')->where('organization_id', $organizationId)->count(),
            'service_calendar_entries' => DB::table('calendar_entries')->where('organization_id', $organizationId)->where('source_type', 'service')->count(),
            'schedule_actions' => $this->scheduleActionItems($organizationId)->count(),
            'schedule_whatsapp_logs' => WhatsappDeliveryLog::query()->withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->where(fn ($q) => $q->where('event_key', 'like', 'schedule.%')->orWhere('event_key', 'like', 'repertoire.%'))
                ->count(),
            'special_event_whatsapp_logs' => WhatsappDeliveryLog::query()->withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->where('event_key', 'like', 'special_event.%')
                ->count(),
            'active_rules' => DB::table('recurring_service_rules')->where('organization_id', $organizationId)->where('active', true)->whereNull('deleted_at')->count(),
            'users' => DB::table('users')->where('organization_id', $organizationId)->whereNull('deleted_at')->count(),
            'people' => DB::table('people')->where('organization_id', $organizationId)->whereNull('deleted_at')->count(),
            'songs' => DB::table('songs')->where('organization_id', $organizationId)->whereNull('deleted_at')->count(),
            'rehearsals' => DB::table('rehearsals')->where('organization_id', $organizationId)->whereNull('deleted_at')->count(),
            'rehearsal_songs' => DB::table('rehearsal_song_items')->where('organization_id', $organizationId)->count(),
        ];
    }

    public function cleanup(
        string $organizationId,
        bool $removeManualServices = false,
        bool $removeSpecialEvents = false,
        bool $regenerateRecurringServices = false,
    ): array {
        $before = $this->preview($organizationId);
        $warnings = [];

        $recurringServices = Service::query()->withoutGlobalScopes()->withTrashed()
            ->where('organization_id', $organizationId)
            ->where(fn ($q) => $q->where('origin', 'recurrence')->orWhereNotNull('recurring_rule_id'))
            ->get();

        $manualServices = $removeManualServices
            ? Service::query()->withoutGlobalScopes()->withTrashed()
                ->where('organization_id', $organizationId)
                ->whereNull('recurring_rule_id')
                ->whereNull('special_event_id')
                ->where('origin', '!=', 'recurrence')
                ->get()
            : collect();

        $specialEvents = $removeSpecialEvents
            ? SpecialEvent::query()->withoutGlobalScopes()->withTrashed()
                ->where('organization_id', $organizationId)
                ->get()
            : collect();
        $specialEventIds = $specialEvents->pluck('id')->map(fn ($id) => (string) $id)->all();
        $specialEventServices = $specialEventIds !== []
            ? Service::query()->withoutGlobalScopes()->withTrashed()
                ->where('organization_id', $organizationId)
                ->whereIn('special_event_id', $specialEventIds)
                ->get()
            : collect();

        $servicesToRemove = $recurringServices
            ->concat($manualServices)
            ->concat($specialEventServices)
            ->unique('id')
            ->values();
        $serviceIdsToRemove = $servicesToRemove->pluck('id')->map(fn ($id) => (string) $id)->all();
        $manualIds = $manualServices->pluck('id')->all();

        // Projeções externas são best-effort; uma falha no Google não pode impedir a
        // limpeza do banco local. O sync futuro corrigirá o calendário após reconexão.
        foreach ($servicesToRemove as $service) {
            try {
                $this->calendar->deleteServiceProjection($service);
            } catch (\Throwable $exception) {
                report($exception);
                $warnings[] = 'Uma projeção externa de agenda não pôde ser removida imediatamente.';
            }
        }

        DB::transaction(function () use ($organizationId, $serviceIdsToRemove, $removeSpecialEvents, $specialEventIds): void {
            foreach ($this->scheduleActionItems($organizationId) as $item) {
                $item->delete();
            }

            WhatsappDeliveryLog::query()->withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->where(fn ($q) => $q->where('event_key', 'like', 'schedule.%')
                    ->orWhere('event_key', 'like', 'repertoire.%'))
                ->delete();

            if ($removeSpecialEvents) {
                foreach ($this->specialEventActionItems($organizationId) as $item) {
                    $item->delete();
                }
                WhatsappDeliveryLog::query()->withoutGlobalScopes()
                    ->where('organization_id', $organizationId)
                    ->where('event_key', 'like', 'special_event.%')
                    ->delete();
            }

            // Limpa a camada operacional de TODAS as escalas, inclusive de cultos
            // manuais preservados. As FKs de filhos são respeitadas na ordem abaixo.
            $this->deleteOrgTable('swap_requests', $organizationId);
            $this->deleteOrgTable('schedule_confirmations', $organizationId);
            $this->deleteOrgTable('service_additional_ministers', $organizationId);
            $this->deleteOrgTable('schedule_assignments', $organizationId);
            $this->deleteOrgTable('repertoires', $organizationId);
            $this->deleteOrgTable('service_executions', $organizationId);

            if (\Illuminate\Support\Facades\Schema::hasTable('audit_logs')) {
                DB::table('audit_logs')->where('organization_id', $organizationId)
                    ->where(function ($q) use ($removeSpecialEvents): void {
                        $q->where('event', 'like', 'schedule.%')
                            ->orWhere('event', 'service.test_data.reset');
                        if ($removeSpecialEvents) {
                            $q->orWhere('event', 'like', 'special_event.%');
                        }
                    })
                    ->delete();
            }

            if ($serviceIdsToRemove !== []) {
                if (\Illuminate\Support\Facades\Schema::hasTable('calendar_entries')) {
                    DB::table('calendar_entries')
                        ->where('organization_id', $organizationId)
                        ->where('source_type', 'service')
                        ->whereIn('source_id', $serviceIdsToRemove)
                        ->delete();
                }

                // Excluir o culto é a forma mais segura de limpar todos os filhos
                // remanescentes (escala, repertório, execução) por ON DELETE CASCADE.
                DB::table('services')->where('organization_id', $organizationId)
                    ->whereIn('id', $serviceIdsToRemove)
                    ->delete();
            }

            if ($removeSpecialEvents && $specialEventIds !== []) {
                // Responses possuem cascade e services já foram eliminados acima.
                DB::table('special_events')->where('organization_id', $organizationId)
                    ->whereIn('id', $specialEventIds)
                    ->delete();
            }
        });

        // Reprojeta apenas os cultos avulsos/especiais que o administrador optou por
        // preservar. Uma falha externa vira aviso, não transforma a limpeza concluída
        // em uma falsa mensagem de falha.
        Service::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->get()
            ->each(function (Service $service) use (&$warnings): void {
                try {
                    $this->calendar->syncService($service);
                } catch (\Throwable $exception) {
                    report($exception);
                    $warnings[] = 'Um culto preservado não pôde ser reprojetado na agenda externa.';
                }
            });

        // RC92: limpeza deve realmente deixar a camada materializada zerada.
        // Antes a própria rotina recriava cinco meses imediatamente e a tela voltava
        // a mostrar dezenas de ocorrências (ex.: 86), parecendo que a limpeza falhou.
        // A recriação agora é opcional; gerar uma nova escala sincroniza o mês escolhido
        // e Cultos > Gerar recorrências continua disponível quando o administrador quiser.
        $generated = 0;
        if ($regenerateRecurringServices) {
            $cursor = Carbon::now()->startOfMonth();
            for ($i = 0; $i < 3; $i++) {
                try {
                    $generated += $this->recurring->generateMonth($cursor->copy()->addMonths($i));
                } catch (\Throwable $exception) {
                    report($exception);
                    $warnings[] = 'Não foi possível recriar automaticamente todas as ocorrências de '.($cursor->copy()->addMonths($i)->format('m/Y')).'. Use Cultos > Gerar recorrências após revisar a regra.';
                }
            }
        }

        return [
            'before' => $before,
            'generated_services' => $generated,
            'removed_manual_services' => count($manualIds),
            'removed_special_events' => count($specialEventIds),
            'warnings' => array_values(array_unique($warnings)),
            'after' => $this->preview($organizationId),
        ];
    }

    private function deleteOrgTable(string $table, string $organizationId): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable($table)
            || ! \Illuminate\Support\Facades\Schema::hasColumn($table, 'organization_id')) {
            return;
        }
        DB::table($table)->where('organization_id', $organizationId)->delete();
    }

    /** @return \Illuminate\Support\Collection<int,ActionItem> */
    private function scheduleActionItems(string $organizationId)
    {
        return ActionItem::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->get()
            ->filter(function (ActionItem $item): bool {
                $payload = is_array($item->payload) ? $item->payload : [];
                $eventKey = (string) ($payload['event_key'] ?? '');
                if (isset($payload['assignment_id']) || isset($payload['swap_request_id']) || isset($payload['service_id'])) {
                    return true;
                }
                if (str_starts_with($eventKey, 'schedule.') || str_starts_with($eventKey, 'repertoire.')) {
                    return true;
                }
                $url = (string) $item->action_url;
                return str_contains($url, '/schedule') || str_contains($url, '/swaps');
            })
            ->values();
    }

    /** @return \Illuminate\Support\Collection<int,ActionItem> */
    private function specialEventActionItems(string $organizationId)
    {
        return ActionItem::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->get()
            ->filter(function (ActionItem $item): bool {
                $payload = is_array($item->payload) ? $item->payload : [];
                $eventKey = (string) ($payload['event_key'] ?? '');
                $url = (string) $item->action_url;
                $text = mb_strtolower((string) $item->title.' '.(string) $item->message);

                return str_starts_with($eventKey, 'special_event.')
                    || str_contains($url, '/special-events')
                    || str_contains($text, 'evento especial');
            })
            ->values();
    }
}
