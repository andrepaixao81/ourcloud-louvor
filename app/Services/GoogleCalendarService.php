<?php

namespace App\Services;

use App\Jobs\SyncGoogleCalendarEventJob;
use App\Models\CalendarEntry;
use App\Models\CalendarIntegration;
use App\Models\ExternalCalendarSync;
use App\Models\ScheduleAssignment;
use App\Models\Service;
use App\Models\ServiceAdditionalMinister;
use App\Models\User;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Exception as GoogleServiceException;
use RuntimeException;

class GoogleCalendarService
{
    public function __construct(private readonly OrganizationGoogleCalendarSettingsService $settings)
    {
    }

    private function client(string $organizationId): Client
    {
        $settings = $this->settings->get($organizationId, true);
        if (! $settings['enabled']) throw new RuntimeException('Integração Google Agenda desabilitada na plataforma.');
        if ($settings['client_id'] === '' || empty($settings['client_secret'])) throw new RuntimeException('Client ID/Client Secret do Google Agenda não configurados.');

        $client = new Client();
        $client->setClientId($settings['client_id']);
        $client->setClientSecret((string) $settings['client_secret']);
        $client->setRedirectUri($settings['redirect_uri']);
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setIncludeGrantedScopes(true);
        $client->setScopes([Calendar::CALENDAR_EVENTS]);
        return $client;
    }

    public function authorizationUrl(string $state, string $organizationId): string
    {
        $client = $this->client($organizationId);
        $client->setState($state);
        return $client->createAuthUrl();
    }

    public function connectFromCode(string $code, string $type = 'organization', ?string $calendarId = null): CalendarIntegration
    {
        $organizationId = auth()->user()->organization_id;
        $settings = $this->settings->get($organizationId, true);
        $client = $this->client($organizationId);
        $token = $client->fetchAccessTokenWithAuthCode($code);
        if (isset($token['error'])) throw new RuntimeException('Falha na autorização Google: '.$token['error']);

        $expiresAt = isset($token['expires_in']) ? now()->addSeconds((int) $token['expires_in'] - 60) : null;
        $integration = CalendarIntegration::query()->create([
            'organization_id' => $organizationId,
            'user_id' => in_array($type, ['personal', 'team'], true) ? auth()->id() : null,
            'provider' => 'google',
            'integration_type' => $type,
            'external_calendar_id' => in_array($type, ['personal', 'team'], true) ? 'primary' : ($calendarId ?: $settings['calendar_id'] ?: 'primary'),
            'access_token_encrypted' => json_encode($token),
            'refresh_token_encrypted' => $token['refresh_token'] ?? null,
            'token_expires_at' => $expiresAt,
            'status' => 'connected',
        ]);

        // RC44: ao conectar, projeta imediatamente o estado publicado atual.
        CalendarEntry::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('source_type', 'service')
            ->get()
            ->each(function (CalendarEntry $entry) use ($integration): void {
                $sync = ExternalCalendarSync::query()->withoutGlobalScopes()->updateOrCreate(
                    ['organization_id' => $entry->organization_id, 'calendar_entry_id' => $entry->id, 'integration_id' => $integration->id],
                    ['sync_status' => 'pending', 'retry_count' => 0, 'last_error' => null]
                );
                SyncGoogleCalendarEventJob::dispatch($sync->id)->afterCommit();
            });

        return $integration;
    }

    public function sync(ExternalCalendarSync $sync): void
    {
        $integration = CalendarIntegration::query()->withoutGlobalScopes()->findOrFail($sync->integration_id);
        $entry = CalendarEntry::withTrashed()->withoutGlobalScopes()->findOrFail($sync->calendar_entry_id);
        if ($integration->status !== 'connected' || $integration->revoked_at) return;

        $client = $this->client($integration->organization_id);
        $token = json_decode((string) $integration->access_token_encrypted, true) ?: [];
        if ($token) $client->setAccessToken($token);

        if ($client->isAccessTokenExpired()) {
            $refresh = $integration->refresh_token_encrypted ?: ($token['refresh_token'] ?? null);
            if (! $refresh) throw new RuntimeException('Refresh token ausente. Reconecte o Google Agenda.');
            $new = $client->fetchAccessTokenWithRefreshToken($refresh);
            if (isset($new['error'])) throw new RuntimeException('Falha ao renovar token Google: '.$new['error']);
            $new['refresh_token'] = $refresh;
            $integration->update([
                'access_token_encrypted' => json_encode($new),
                'refresh_token_encrypted' => $refresh,
                'token_expires_at' => now()->addSeconds((int) ($new['expires_in'] ?? 3600) - 60),
            ]);
        }

        $calendar = new Calendar($client);
        $calendarId = $integration->external_calendar_id ?: $this->settings->get($integration->organization_id)['calendar_id'] ?: 'primary';

        if (! $this->shouldExistInIntegration($entry, $integration)) {
            $this->deleteExternalEvent($calendar, $calendarId, $sync);
            $integration->update(['last_sync_at' => now(), 'last_error_at' => null]);
            return;
        }

        $eventBody = new Event([
            'summary' => $entry->title,
            'description' => $entry->description,
            'start' => ['dateTime' => $entry->starts_at->toRfc3339String(), 'timeZone' => $entry->timezone],
            'end' => ['dateTime' => ($entry->ends_at ?: $entry->starts_at->copy()->addHours(2))->toRfc3339String(), 'timeZone' => $entry->timezone],
            'extendedProperties' => ['private' => ['sgml_entry_id' => $entry->id]],
        ]);

        try {
            if ($sync->external_event_id) {
                $external = $calendar->events->update($calendarId, $sync->external_event_id, $eventBody);
            } else {
                $external = $calendar->events->insert($calendarId, $eventBody);
            }
        } catch (GoogleServiceException $e) {
            // Se o usuário removeu manualmente o evento externo, recriamos a
            // projeção em vez de deixar a sincronização permanentemente quebrada.
            if ($sync->external_event_id && $e->getCode() === 404) {
                $external = $calendar->events->insert($calendarId, $eventBody);
            } else {
                throw $e;
            }
        }

        $sync->update([
            'external_event_id' => $external->getId(),
            'external_etag' => $external->getEtag(),
            'sync_status' => 'synced',
            'last_synced_at' => now(),
            'last_error' => null,
        ]);
        $integration->update(['last_sync_at' => now(), 'last_error_at' => null]);
    }

    public function disconnectIntegration(CalendarIntegration $integration): void
    {
        $integration = CalendarIntegration::query()->withoutGlobalScopes()->findOrFail($integration->id);

        // Enquanto ainda possuímos credenciais válidas, removemos somente os
        // eventos que o próprio SGML projetou. Assim trocar entre "minha escala"
        // e "agenda completa" não deixa cópias órfãs no Google Agenda.
        if ($integration->status === 'connected' && ! $integration->revoked_at) {
            $client = $this->client($integration->organization_id);
            $token = json_decode((string) $integration->access_token_encrypted, true) ?: [];
            if ($token) $client->setAccessToken($token);

            if ($client->isAccessTokenExpired()) {
                $refresh = $integration->refresh_token_encrypted ?: ($token['refresh_token'] ?? null);
                if (! $refresh) {
                    throw new RuntimeException('Não foi possível limpar os eventos do Google Agenda porque o refresh token está ausente. Reconecte a integração e tente novamente.');
                }
                $new = $client->fetchAccessTokenWithRefreshToken($refresh);
                if (isset($new['error'])) {
                    throw new RuntimeException('Não foi possível renovar a autorização Google para remover os eventos sincronizados.');
                }
                $new['refresh_token'] = $refresh;
                $integration->update([
                    'access_token_encrypted' => json_encode($new),
                    'refresh_token_encrypted' => $refresh,
                    'token_expires_at' => now()->addSeconds((int) ($new['expires_in'] ?? 3600) - 60),
                ]);
                $client->setAccessToken($new);
            }

            $calendar = new Calendar($client);
            $calendarId = $integration->external_calendar_id ?: $this->settings->get($integration->organization_id)['calendar_id'] ?: 'primary';
            $syncs = ExternalCalendarSync::query()->withoutGlobalScopes()
                ->where('integration_id', $integration->id)
                ->whereNotNull('external_event_id')
                ->get();

            foreach ($syncs as $sync) {
                $this->deleteExternalEvent($calendar, $calendarId, $sync);
            }
        }

        $integration->update([
            'status' => 'revoked',
            'revoked_at' => now(),
            'access_token_encrypted' => null,
            'refresh_token_encrypted' => null,
            'last_error_at' => null,
        ]);
    }

    private function shouldExistInIntegration(CalendarEntry $entry, CalendarIntegration $integration): bool
    {
        if ($entry->trashed() || $entry->status === 'cancelled' || $entry->source_type !== 'service') {
            return false;
        }

        $service = Service::query()->withoutGlobalScopes()->withTrashed()->find($entry->source_id);
        if (! $service || $service->trashed() || $service->status === 'cancelled') {
            return false;
        }

        if ($integration->integration_type === 'personal') {
            $user = $integration->user_id
                ? User::query()->withoutGlobalScopes()->with('person')->find($integration->user_id)
                : null;
            $personId = $user?->person?->id;
            if (! $personId) return false;

            return ScheduleAssignment::query()->withoutGlobalScopes()
                ->where('service_id', $service->id)
                ->where('minister_person_id', $personId)
                ->where('status', 'published')
                ->exists()
                || ServiceAdditionalMinister::query()->withoutGlobalScopes()
                    ->where('service_id', $service->id)
                    ->where('person_id', $personId)
                    ->where('status', 'published')
                    ->exists();
        }

        return ScheduleAssignment::query()->withoutGlobalScopes()
            ->where('service_id', $service->id)
            ->where('status', 'published')
            ->exists()
            || ServiceAdditionalMinister::query()->withoutGlobalScopes()
                ->where('service_id', $service->id)
                ->where('status', 'published')
                ->exists();
    }

    private function deleteExternalEvent(Calendar $calendar, string $calendarId, ExternalCalendarSync $sync): void
    {
        if ($sync->external_event_id) {
            try {
                $calendar->events->delete($calendarId, $sync->external_event_id);
            } catch (GoogleServiceException $e) {
                if ($e->getCode() !== 404) throw $e;
            }
        }

        $sync->update([
            'external_event_id' => null,
            'external_etag' => null,
            'sync_status' => 'synced',
            'last_synced_at' => now(),
            'last_error' => null,
        ]);
    }
}
