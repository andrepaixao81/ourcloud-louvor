<?php

namespace App\Console\Commands;

use App\Jobs\SendWhatsappNotificationJob;
use App\Models\Organization;
use App\Models\Person;
use App\Models\WhatsappDeliveryLog;
use App\Models\WhatsappSetting;
use App\Services\BirthdaySettingsService;
use App\Services\TeamNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendBirthdayGreetings extends Command
{
    protected $signature = 'sgml:birthday-greetings {--organization=}';
    protected $description = 'Envia felicitações de aniversário pelo WhatsApp sem duplicar o envio do dia';

    public function handle(BirthdaySettingsService $birthdaySettings, TeamNotificationService $teamNotifications): int
    {
        $organizations = Organization::query()->where('active', true)
            ->when($this->option('organization'), fn ($query, $id) => $query->where('id', $id))
            ->get();

        $queued = 0;
        $skipped = 0;

        foreach ($organizations as $organization) {
            $settings = $birthdaySettings->get($organization->id);
            if (! $settings['enabled']) {
                continue;
            }

            $timezone = $organization->timezone ?: config('app.timezone');
            $now = Carbon::now($timezone);
            [$hour, $minute] = array_map('intval', explode(':', $settings['send_time'] ?: '08:00'));
            $sendFrom = $now->copy()->startOfDay()->setTime($hour, $minute);
            if ($now->lt($sendFrom)) {
                continue;
            }

            $whatsapp = WhatsappSetting::query()->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->first();
            if (! $whatsapp?->isConfigured()) {
                continue;
            }

            $people = Person::query()->withoutGlobalScopes()
                ->with('user')
                ->where('organization_id', $organization->id)
                ->where('status', 'active')
                ->whereNotNull('birth_date')
                ->whereMonth('birth_date', $now->month)
                ->whereDay('birth_date', $now->day)
                ->get();

            foreach ($people as $person) {
                $phone = $teamNotifications->normalizePhone((string) $person->phone);
                if ($phone === null) {
                    $skipped++;
                    continue;
                }

                $eventKey = 'birthday:'.$now->format('Y-m-d').':'.$person->id;
                $alreadyQueued = WhatsappDeliveryLog::query()->withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->where('event_key', $eventKey)
                    ->whereIn('status', ['queued', 'sent', 'failed'])
                    ->exists();
                if ($alreadyQueued) {
                    continue;
                }

                $summary = $birthdaySettings->renderMessage($settings, [
                    'nome' => $person->display_name,
                    'primeiro_nome' => $person->first_name,
                    'dia' => $now->format('d'),
                    'mes' => $this->monthName($now->month),
                    'igreja' => $organization->name,
                ]);

                $log = WhatsappDeliveryLog::query()->withoutGlobalScopes()->create([
                    'organization_id' => $organization->id,
                    'user_id' => $person->user?->active ? $person->user->id : null,
                    'phone' => $phone,
                    'provider' => $whatsapp->selectedProvider(),
                    'event_key' => $eventKey,
                    'title' => 'Feliz aniversário, '.$person->first_name.'! 🎉',
                    'summary' => $summary,
                    'action_url' => null,
                    'status' => 'queued',
                ]);

                SendWhatsappNotificationJob::dispatch($log->id)->afterCommit();
                $queued++;
            }
        }

        $this->info("Felicitações enfileiradas: {$queued}. Sem telefone/configuração aplicável: {$skipped}.");
        return self::SUCCESS;
    }

    private function monthName(int $month): string
    {
        return [1=>'janeiro',2=>'fevereiro',3=>'março',4=>'abril',5=>'maio',6=>'junho',7=>'julho',8=>'agosto',9=>'setembro',10=>'outubro',11=>'novembro',12=>'dezembro'][$month] ?? '';
    }
}
