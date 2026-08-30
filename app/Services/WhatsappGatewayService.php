<?php

namespace App\Services;

use App\Models\WhatsappDeliveryLog;
use App\Models\WhatsappSetting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class WhatsappGatewayService
{
    /** @return array{provider:string,message_id:?string} */
    public function send(WhatsappSetting $settings, WhatsappDeliveryLog $log): array
    {
        if (! $settings->isConfigured()) {
            throw new RuntimeException('Integração WhatsApp desabilitada ou incompleta.');
        }

        return $settings->selectedProvider() === WhatsappSetting::PROVIDER_EVOLUTION
            ? $this->sendEvolution($settings, $log)
            : $this->sendMeta($settings, $log);
    }

    /** @return array{ok:bool,message:string,state:?string} */
    public function checkConnection(WhatsappSetting $settings): array
    {
        if (! $settings->isConfigured()) {
            return ['ok' => false, 'message' => 'Configure e habilite o provedor antes de testar.', 'state' => null];
        }

        if ($settings->selectedProvider() === WhatsappSetting::PROVIDER_META) {
            return ['ok' => true, 'message' => 'Configuração Meta preenchida. Use Enviar teste para validar o envio real.', 'state' => null];
        }

        $baseUrl = $this->evolutionBaseUrl($settings);
        $instance = rawurlencode((string) $settings->evolution_instance);

        try {
            $root = Http::withHeaders(['apikey' => (string) $settings->evolution_api_key])
                ->acceptJson()
                ->timeout(10)
                ->get($baseUrl.'/');

            if (! $root->successful()) {
                return [
                    'ok' => false,
                    'message' => 'Evolution API respondeu HTTP '.$root->status().' na URL base.',
                    'state' => null,
                ];
            }

            $stateResponse = Http::withHeaders(['apikey' => (string) $settings->evolution_api_key])
                ->acceptJson()
                ->timeout(10)
                ->get($baseUrl.'/instance/connectionState/'.$instance);

            if (! $stateResponse->successful()) {
                return [
                    'ok' => true,
                    'message' => 'Evolution API acessível, mas o endpoint de estado respondeu HTTP '.$stateResponse->status().'. Use Enviar teste para validar a instância.',
                    'state' => null,
                ];
            }

            $state = data_get($stateResponse->json(), 'instance.state')
                ?? data_get($stateResponse->json(), 'state')
                ?? data_get($stateResponse->json(), 'instance.status');

            return [
                'ok' => true,
                'message' => $state ? 'Evolution API acessível. Estado da instância: '.$state.'.' : 'Evolution API e instância responderam corretamente.',
                'state' => is_scalar($state) ? (string) $state : null,
            ];
        } catch (\Throwable $exception) {
            return ['ok' => false, 'message' => Str::limit($exception->getMessage(), 500, ''), 'state' => null];
        }
    }

    /** @return array{provider:string,message_id:?string} */
    private function sendEvolution(WhatsappSetting $settings, WhatsappDeliveryLog $log): array
    {
        $url = $this->evolutionBaseUrl($settings)
            .'/message/sendText/'.rawurlencode((string) $settings->evolution_instance);

        $payload = [
            'number' => $log->phone,
            'text' => $this->plainTextMessage($log),
            'delay' => max(0, min(10000, (int) ($settings->evolution_delay_ms ?? 500))),
            'linkPreview' => (bool) ($settings->evolution_link_preview ?? true),
        ];

        $response = Http::withHeaders([
                'apikey' => (string) $settings->evolution_api_key,
            ])
            ->acceptJson()
            ->asJson()
            ->timeout(25)
            ->post($url, $payload);

        $this->throwForProviderFailure($response, 'Evolution API');
        $json = $response->json();

        $messageId = data_get($json, 'key.id')
            ?? data_get($json, '0.key.id')
            ?? data_get($json, 'message.key.id')
            ?? data_get($json, 'messageId');

        return [
            'provider' => WhatsappSetting::PROVIDER_EVOLUTION,
            'message_id' => is_scalar($messageId) ? (string) $messageId : null,
        ];
    }

    /** @return array{provider:string,message_id:?string} */
    private function sendMeta(WhatsappSetting $settings, WhatsappDeliveryLog $log): array
    {
        $version = preg_match('/^v\d+\.\d+$/', (string) $settings->graph_version)
            ? $settings->graph_version
            : 'v26.0';
        $url = 'https://graph.facebook.com/'.$version.'/'.$settings->phone_number_id.'/messages';

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $log->phone,
            'type' => 'template',
            'template' => [
                'name' => $settings->template_name ?: 'sgml_aviso',
                'language' => ['code' => $settings->language_code ?: 'pt_BR'],
                'components' => [[
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => Str::limit((string) $log->title, 180, '')],
                        ['type' => 'text', 'text' => Str::limit((string) ($log->summary ?: 'Há uma nova atualização no SGML.'), 900, '')],
                        ['type' => 'text', 'text' => Str::limit((string) ($log->action_url ?: config('app.url')), 500, '')],
                    ],
                ]],
            ],
        ];

        $response = Http::withToken((string) $settings->access_token)
            ->acceptJson()
            ->asJson()
            ->timeout(25)
            ->post($url, $payload);

        $this->throwForProviderFailure($response, 'Meta Cloud API');
        $messageId = data_get($response->json(), 'messages.0.id');

        return [
            'provider' => WhatsappSetting::PROVIDER_META,
            'message_id' => is_scalar($messageId) ? (string) $messageId : null,
        ];
    }

    private function plainTextMessage(WhatsappDeliveryLog $log): string
    {
        $parts = [
            '🎵 *OurCloud Louvor*',
            '*'.Str::limit((string) $log->title, 180, '').'*',
            Str::limit((string) ($log->summary ?: 'Há uma nova atualização no SGML.'), 6000, ''),
        ];

        if (filled($log->action_url)) {
            $parts[] = "Acesse o SGML para ver os detalhes:\n".Str::limit((string) $log->action_url, 500, '');
        }

        return implode("\n\n", $parts);
    }

    private function evolutionBaseUrl(WhatsappSetting $settings): string
    {
        $url = rtrim(trim((string) $settings->evolution_base_url), '/');
        if (! preg_match('#^https?://#i', $url)) {
            throw new RuntimeException('URL da Evolution API inválida. Use http:// ou https://.');
        }
        return $url;
    }

    private function throwForProviderFailure(Response $response, string $provider): void
    {
        if ($response->successful()) {
            return;
        }

        $body = Str::limit($response->body(), 2000, '');
        throw new RuntimeException($provider.' respondeu HTTP '.$response->status().': '.$body);
    }
}
