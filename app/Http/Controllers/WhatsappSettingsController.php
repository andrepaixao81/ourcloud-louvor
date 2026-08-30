<?php

namespace App\Http\Controllers;

use App\Jobs\SendWhatsappNotificationJob;
use App\Models\WhatsappDeliveryLog;
use App\Models\WhatsappSetting;
use App\Services\TeamNotificationService;
use App\Services\WhatsappGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WhatsappSettingsController extends Controller
{
    public function index(Request $request)
    {
        $setting = WhatsappSetting::query()->firstOrCreate(
            ['organization_id' => $request->user()->organization_id],
            [
                'enabled' => false,
                'provider' => WhatsappSetting::PROVIDER_META,
                'graph_version' => 'v26.0',
                'template_name' => 'sgml_aviso',
                'language_code' => 'pt_BR',
                'evolution_delay_ms' => 500,
                'evolution_link_preview' => true,
            ]
        );

        $logs = WhatsappDeliveryLog::query()->latest()->limit(30)->get();
        return view('admin.whatsapp', compact('setting', 'logs'));
    }

    public function update(Request $request)
    {
        $setting = WhatsappSetting::query()->firstOrCreate(['organization_id' => $request->user()->organization_id]);
        $data = $request->validate([
            'provider' => ['required', Rule::in([WhatsappSetting::PROVIDER_META, WhatsappSetting::PROVIDER_EVOLUTION])],
            'enabled' => ['nullable', 'boolean'],
            'graph_version' => ['nullable', 'regex:/^v\d+\.\d+$/'],
            'phone_number_id' => ['nullable', 'string', 'max:120'],
            'access_token' => ['nullable', 'string', 'max:10000'],
            'template_name' => ['nullable', 'string', 'max:160'],
            'language_code' => ['nullable', 'string', 'max:20'],
            'evolution_base_url' => ['nullable', 'string', 'max:500', 'regex:#^https?://#i'],
            'evolution_instance' => ['nullable', 'string', 'max:160'],
            'evolution_api_key' => ['nullable', 'string', 'max:10000'],
            'evolution_delay_ms' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'evolution_link_preview' => ['nullable', 'boolean'],
        ]);

        $provider = $data['provider'];
        $enabled = $request->boolean('enabled');

        if ($enabled && $provider === WhatsappSetting::PROVIDER_META) {
            $token = filled($data['access_token'] ?? null) ? trim((string) $data['access_token']) : $setting->access_token;
            if (blank($data['phone_number_id'] ?? null) || blank($token) || blank($data['template_name'] ?? null) || blank($data['language_code'] ?? null)) {
                throw ValidationException::withMessages([
                    'provider' => 'Para habilitar a Meta Cloud API, informe Phone Number ID, Access Token, template e idioma.',
                ]);
            }
        }

        if ($enabled && $provider === WhatsappSetting::PROVIDER_EVOLUTION) {
            $apiKey = filled($data['evolution_api_key'] ?? null) ? trim((string) $data['evolution_api_key']) : $setting->evolution_api_key;
            if (blank($data['evolution_base_url'] ?? null) || blank($data['evolution_instance'] ?? null) || blank($apiKey)) {
                throw ValidationException::withMessages([
                    'provider' => 'Para habilitar a Evolution API, informe URL, instância e API Key.',
                ]);
            }
        }

        $payload = [
            'enabled' => $enabled,
            'provider' => $provider,
            'graph_version' => trim((string) ($data['graph_version'] ?? 'v26.0')) ?: 'v26.0',
            'phone_number_id' => filled($data['phone_number_id'] ?? null) ? trim((string) $data['phone_number_id']) : null,
            'template_name' => trim((string) ($data['template_name'] ?? 'sgml_aviso')) ?: 'sgml_aviso',
            'language_code' => trim((string) ($data['language_code'] ?? 'pt_BR')) ?: 'pt_BR',
            'evolution_base_url' => filled($data['evolution_base_url'] ?? null) ? rtrim(trim((string) $data['evolution_base_url']), '/') : null,
            'evolution_instance' => filled($data['evolution_instance'] ?? null) ? trim((string) $data['evolution_instance']) : null,
            'evolution_delay_ms' => (int) ($data['evolution_delay_ms'] ?? 500),
            'evolution_link_preview' => $request->boolean('evolution_link_preview'),
            'updated_by' => $request->user()->id,
        ];

        if (filled($data['access_token'] ?? null)) {
            $payload['access_token'] = trim((string) $data['access_token']);
        }
        if (filled($data['evolution_api_key'] ?? null)) {
            $payload['evolution_api_key'] = trim((string) $data['evolution_api_key']);
        }

        $setting->update($payload);

        return back()->with('status', 'Configuração do WhatsApp atualizada. Provedor: '.($provider === 'evolution' ? 'Evolution API' : 'Meta Cloud API').'.');
    }

    public function check(Request $request, WhatsappGatewayService $gateway)
    {
        $setting = WhatsappSetting::query()->where('organization_id', $request->user()->organization_id)->firstOrFail();
        $result = $gateway->checkConnection($setting);

        return back()->with($result['ok'] ? 'status' : 'error', $result['message']);
    }

    public function test(Request $request, TeamNotificationService $teamNotifications)
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:40']]);
        $setting = WhatsappSetting::query()->where('organization_id', $request->user()->organization_id)->firstOrFail();
        abort_unless($setting->isConfigured(), 422, 'Configure e habilite a integração antes de testar.');

        $phone = $teamNotifications->normalizePhone($data['phone']);
        abort_unless($phone, 422, 'Informe um telefone válido com DDD/DDI.');

        $log = WhatsappDeliveryLog::query()->create([
            'organization_id' => $request->user()->organization_id,
            'user_id' => $request->user()->id,
            'phone' => $phone,
            'provider' => $setting->selectedProvider(),
            'event_key' => 'whatsapp.test:'.Str::uuid(),
            'title' => 'Teste do OurCloud Louvor',
            'summary' => 'A integração automática de avisos do SGML está funcionando.',
            'action_url' => route('home'),
            'status' => 'queued',
        ]);
        SendWhatsappNotificationJob::dispatch($log->id);

        return back()->with('status', 'Mensagem de teste colocada na fila pelo provedor selecionado. Consulte o histórico abaixo.');
    }
}
