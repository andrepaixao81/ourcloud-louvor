<?php

namespace App\Services;

use App\Models\ApplicationSetting;

class BirthdaySettingsService
{
    private const PREFIX = 'birthday.';

    public function get(string $organizationId): array
    {
        $rows = ApplicationSetting::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereIn('key', [
                self::PREFIX.'enabled',
                self::PREFIX.'send_time',
                self::PREFIX.'message',
            ])
            ->get()
            ->keyBy('key');

        $value = static fn (string $key, mixed $default = null) => $rows->get(self::PREFIX.$key)?->value ?? $default;

        return [
            'enabled' => filter_var($value('enabled', '1'), FILTER_VALIDATE_BOOLEAN),
            'send_time' => (string) $value('send_time', '08:00'),
            'message' => (string) $value('message', self::defaultMessage()),
        ];
    }

    public function save(string $organizationId, array $data): void
    {
        $values = [
            'enabled' => ! empty($data['enabled']) ? '1' : '0',
            'send_time' => (string) ($data['send_time'] ?? '08:00'),
            'message' => trim((string) ($data['message'] ?? self::defaultMessage())),
        ];

        foreach ($values as $key => $value) {
            ApplicationSetting::query()->withoutGlobalScopes()->updateOrCreate(
                ['organization_id' => $organizationId, 'key' => self::PREFIX.$key],
                ['value' => $value, 'is_secret' => false]
            );
        }
    }

    public function renderMessage(array $settings, array $variables): string
    {
        $message = (string) ($settings['message'] ?? self::defaultMessage());

        foreach ($variables as $key => $value) {
            $message = str_replace('{'.$key.'}', (string) $value, $message);
        }

        return trim($message);
    }

    public static function defaultMessage(): string
    {
        return "Olá, {primeiro_nome}! 🎉🎂\n\nHoje queremos agradecer a Deus pela sua vida.\n\nQue o Senhor continue abençoando sua caminhada, sua família e seu ministério.\n\nFeliz aniversário! 🥳\n\nCom carinho,\n{igreja}";
    }
}
