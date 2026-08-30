<?php

namespace App\Services;

use App\Models\ApplicationSetting;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class OrganizationMailSettingsService
{
    private const KEYS = [
        'enabled', 'host', 'port', 'username', 'password', 'security', 'from_address', 'from_name', 'ehlo_domain',
    ];

    public function get(string $organizationId, bool $includeSecret = false): array
    {
        $settings = ApplicationSetting::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereIn('key', collect(self::KEYS)->map(fn ($key) => 'smtp.'.$key))
            ->get()
            ->keyBy('key');

        $value = static fn (string $key, mixed $default = null) => $settings->get('smtp.'.$key)?->value ?? $default;

        $password = null;
        if ($includeSecret && ($encrypted = $value('password'))) {
            try {
                $password = Crypt::decryptString($encrypted);
            } catch (\Throwable) {
                $password = null;
            }
        }

        return [
            'enabled' => filter_var($value('enabled', '0'), FILTER_VALIDATE_BOOLEAN),
            'host' => (string) $value('host', ''),
            'port' => (int) $value('port', 587),
            'username' => (string) $value('username', ''),
            'password' => $password,
            'has_password' => filled($value('password')),
            'security' => (string) $value('security', 'smtp'),
            'from_address' => (string) $value('from_address', ''),
            'from_name' => (string) $value('from_name', 'SGML'),
            'ehlo_domain' => (string) $value('ehlo_domain', ''),
        ];
    }

    public function save(string $organizationId, array $data): void
    {
        $values = [
            'enabled' => ! empty($data['enabled']) ? '1' : '0',
            'host' => trim((string) ($data['host'] ?? '')),
            'port' => (string) ((int) ($data['port'] ?? 587)),
            'username' => trim((string) ($data['username'] ?? '')),
            'security' => (string) ($data['security'] ?? 'smtp'),
            'from_address' => trim((string) ($data['from_address'] ?? '')),
            'from_name' => trim((string) ($data['from_name'] ?? 'SGML')),
            'ehlo_domain' => trim((string) ($data['ehlo_domain'] ?? '')),
        ];

        foreach ($values as $key => $value) {
            ApplicationSetting::query()->withoutGlobalScopes()->updateOrCreate(
                ['organization_id' => $organizationId, 'key' => 'smtp.'.$key],
                ['value' => $value, 'is_secret' => false]
            );
        }

        if (array_key_exists('password', $data) && filled($data['password'])) {
            ApplicationSetting::query()->withoutGlobalScopes()->updateOrCreate(
                ['organization_id' => $organizationId, 'key' => 'smtp.password'],
                ['value' => Crypt::encryptString((string) $data['password']), 'is_secret' => true]
            );
        }
    }

    public function isConfigured(string $organizationId): bool
    {
        $settings = $this->get($organizationId);

        return $settings['enabled']
            && $settings['host'] !== ''
            && $settings['port'] > 0
            && $settings['from_address'] !== '';
    }

    public function apply(string $organizationId): bool
    {
        if (! $this->isConfigured($organizationId)) {
            return false;
        }

        $settings = $this->get($organizationId, true);
        $scheme = $settings['security'] === 'smtps' ? 'smtps' : 'smtp';

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.transport', 'smtp');
        Config::set('mail.mailers.smtp.scheme', $scheme);
        Config::set('mail.mailers.smtp.host', $settings['host']);
        Config::set('mail.mailers.smtp.port', $settings['port']);
        Config::set('mail.mailers.smtp.username', $settings['username'] !== '' ? $settings['username'] : null);
        Config::set('mail.mailers.smtp.password', $settings['password']);
        Config::set('mail.mailers.smtp.timeout', 20);
        Config::set('mail.mailers.smtp.local_domain', $settings['ehlo_domain'] !== '' ? $settings['ehlo_domain'] : parse_url((string) config('app.url'), PHP_URL_HOST));
        Config::set('mail.from.address', $settings['from_address']);
        Config::set('mail.from.name', $settings['from_name'] ?: 'SGML');

        app('mail.manager')->purge('smtp');

        return true;
    }

    public function sendTest(string $organizationId, string $recipient): void
    {
        if (! $this->apply($organizationId)) {
            throw new RuntimeException('SMTP não está habilitado ou está incompleto.');
        }

        Mail::raw(
            "Teste de SMTP do SGML.\n\nSe você recebeu esta mensagem, a configuração de envio da organização está funcionando.",
            fn ($message) => $message->to($recipient)->subject('SGML - teste de SMTP')
        );
    }

    public function sendTemporaryPassword(User $user, string $temporaryPassword): void
    {
        if (! $this->apply($user->organization_id)) {
            throw new RuntimeException('SMTP não está configurado para esta organização.');
        }

        $url = rtrim((string) config('app.url'), '/').'/login';
        $text = "Olá, {$user->name}.\n\n"
            ."Foi criado um acesso ao SGML - Sistema de Gestão do Ministério de Louvor.\n\n"
            ."Acesso: {$url}\n"
            ."E-mail: {$user->email}\n"
            ."Senha temporária: {$temporaryPassword}\n\n"
            ."No primeiro acesso o sistema solicitará a definição de uma nova senha.\n"
            ."Não compartilhe esta senha temporária.\n";

        Mail::raw($text, fn ($message) => $message->to($user->email)->subject('Seu acesso ao SGML'));
    }
}
