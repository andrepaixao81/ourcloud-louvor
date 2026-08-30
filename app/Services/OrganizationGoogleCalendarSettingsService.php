<?php

namespace App\Services;

use App\Models\ApplicationSetting;
use Illuminate\Support\Facades\Crypt;

class OrganizationGoogleCalendarSettingsService
{
    private const PREFIX = 'google_calendar.';

    public function get(string $organizationId, bool $includeSecret = false): array
    {
        $rows = ApplicationSetting::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereIn('key', [
                self::PREFIX.'enabled',
                self::PREFIX.'client_id',
                self::PREFIX.'client_secret',
                self::PREFIX.'redirect_uri',
                self::PREFIX.'calendar_id',
            ])
            ->get()
            ->keyBy('key');

        $value = static fn (string $key, mixed $default = null) => $rows->get(self::PREFIX.$key)?->value ?? $default;

        $secret = null;
        if ($includeSecret && filled($value('client_secret'))) {
            try {
                $secret = Crypt::decryptString((string) $value('client_secret'));
            } catch (\Throwable) {
                $secret = null;
            }
        }

        $envEnabled = (bool) config('services.google_calendar.enabled');
        $envClientId = (string) config('services.google_calendar.client_id');
        $envClientSecret = (string) config('services.google_calendar.client_secret');
        $envRedirect = (string) config('services.google_calendar.redirect');
        $envCalendarId = (string) config('services.google_calendar.calendar_id', 'primary');

        return [
            'enabled' => $rows->has(self::PREFIX.'enabled')
                ? filter_var($value('enabled', '0'), FILTER_VALIDATE_BOOLEAN)
                : $envEnabled,
            'client_id' => (string) ($value('client_id') ?: $envClientId),
            'client_secret' => $includeSecret ? ($secret ?: $envClientSecret) : null,
            'has_client_secret' => filled($value('client_secret')) || filled($envClientSecret),
            'redirect_uri' => (string) ($value('redirect_uri') ?: $envRedirect ?: rtrim((string) config('app.url'), '/').'/integrations/google/callback'),
            'calendar_id' => (string) ($value('calendar_id') ?: $envCalendarId ?: 'primary'),
            'source' => $rows->isNotEmpty() ? 'platform' : 'environment',
        ];
    }

    public function save(string $organizationId, array $data): void
    {
        $values = [
            'enabled' => ! empty($data['enabled']) ? '1' : '0',
            'client_id' => trim((string) ($data['client_id'] ?? '')),
            'redirect_uri' => trim((string) ($data['redirect_uri'] ?? '')),
            'calendar_id' => trim((string) ($data['calendar_id'] ?? 'primary')) ?: 'primary',
        ];

        foreach ($values as $key => $value) {
            ApplicationSetting::query()->withoutGlobalScopes()->updateOrCreate(
                ['organization_id' => $organizationId, 'key' => self::PREFIX.$key],
                ['value' => $value, 'is_secret' => false]
            );
        }

        if (array_key_exists('client_secret', $data) && filled($data['client_secret'])) {
            ApplicationSetting::query()->withoutGlobalScopes()->updateOrCreate(
                ['organization_id' => $organizationId, 'key' => self::PREFIX.'client_secret'],
                ['value' => Crypt::encryptString((string) $data['client_secret']), 'is_secret' => true]
            );
        }
    }

    public function configured(string $organizationId): bool
    {
        $settings = $this->get($organizationId);
        return $settings['enabled']
            && $settings['client_id'] !== ''
            && $settings['has_client_secret']
            && $settings['redirect_uri'] !== '';
    }
}
