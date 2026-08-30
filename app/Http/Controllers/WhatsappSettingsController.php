<?php

namespace App\Http\Controllers;

use App\Jobs\SendWhatsappNotificationJob;
use App\Models\WhatsappDeliveryLog;
use App\Models\WhatsappSetting;
use App\Services\TeamNotificationService;
use App\Services\WhatsappGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
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

        $logs = WhatsappDeliveryLog::query()->latest()->limit(50)->get();
        $failedRecent = WhatsappDeliveryLog::query()
            ->whereIn('status', ['failed', 'retrying'])
            ->where('created_at', '>=', now()->subDay())
            ->count();
        $discardableUnsent = WhatsappDeliveryLog::query()
            ->whereIn('status', ['failed', 'cancelled', 'skipped'])
            ->count();
        $queuedLogs = WhatsappDeliveryLog::query()
            ->whereIn('status', ['queued', 'retrying'])
            ->get(['id', 'created_at']);
        $queuedWhatsapp = $queuedLogs->count();
        $queueJobs = 0;
        if (\Illuminate\Support\Facades\Schema::hasTable('jobs') && $queuedLogs->isNotEmpty()) {
            $ids = $queuedLogs->pluck('id')->map(fn ($id) => (string) $id)->all();
            $queueJobsQuery = DB::table('jobs')->where('payload', 'like', '%SendWhatsappNotificationJob%');
            $queueJobsQuery->where(function ($query) use ($ids): void {
                foreach ($ids as $id) $query->orWhere('payload', 'like', '%'.$id.'%');
            });
            $queueJobs = $queueJobsQuery->count();
        }
        $oldestQueuedAt = $queuedLogs->min('created_at');
        $queueMayBeStopped = $oldestQueuedAt && now()->diffInMinutes($oldestQueuedAt) >= 2;
        return view('admin.whatsapp', compact('setting', 'logs', 'failedRecent', 'discardableUnsent', 'queuedWhatsapp', 'queueJobs', 'oldestQueuedAt', 'queueMayBeStopped'));
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

    public function test(Request $request, TeamNotificationService $teamNotifications, WhatsappGatewayService $gateway)
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
            'status' => 'sending',
        ]);

        // RC91: o teste administrativo não depende da fila. Isso separa claramente
        // falha de conexão/provedor de um eventual problema no worker da queue.
        try {
            $result = $gateway->send($setting, $log);
            $log->update([
                'status' => 'sent',
                'provider' => $result['provider'],
                'provider_message_id' => $result['message_id'],
                'error' => null,
                'sent_at' => now(),
            ]);

            return back()->with('status', 'Mensagem de teste enviada imediatamente pelo provedor. A integração está respondendo.');
        } catch (\Throwable $exception) {
            $log->update([
                'status' => 'failed',
                'provider' => $setting->selectedProvider(),
                'error' => Str::limit($exception->getMessage(), 2000, ''),
            ]);
            report($exception);

            return back()->with('error', 'O teste chegou ao provedor, mas falhou: '.Str::limit($exception->getMessage(), 500, ''));
        }
    }

    public function retryFailed(Request $request)
    {
        $setting = WhatsappSetting::query()->where('organization_id', $request->user()->organization_id)->firstOrFail();
        if (! $setting->isConfigured()) {
            throw ValidationException::withMessages(['provider' => 'A integração precisa estar habilitada e com credenciais salvas antes do reenvio.']);
        }

        $logs = WhatsappDeliveryLog::query()
            ->where('organization_id', $request->user()->organization_id)
            ->whereIn('status', ['failed', 'retrying'])
            ->where('created_at', '>=', now()->subDay())
            ->oldest()
            ->limit(100)
            ->get();

        if ($logs->isEmpty()) {
            return back()->with('status', 'Não há falhas recentes para reenviar.');
        }

        $spacing = $setting->batchSpacingSeconds();
        foreach ($logs->values() as $index => $log) {
            $log->update(['status' => 'queued', 'error' => null, 'sent_at' => null]);
            SendWhatsappNotificationJob::dispatch($log->id)->delay(now()->addSeconds($index * $spacing));
        }

        return back()->with('status', $logs->count().' envio(s) recolocado(s) na fila com espaçamento de '.$spacing.'s entre mensagens.');
    }

    public function clearQueue(Request $request)
    {
        $data = $request->validate([
            'confirmation' => ['required', 'in:LIMPAR FILA'],
        ]);
        $organizationId = (string) $request->user()->organization_id;

        $logs = WhatsappDeliveryLog::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereIn('status', ['queued', 'retrying'])
            ->get();
        $logIds = $logs->pluck('id')->map(fn ($id) => (string) $id)->all();

        $deletedJobs = 0;
        DB::transaction(function () use ($logs, $logIds, &$deletedJobs): void {
            // O payload da fila Laravel contém o nome da classe serializada. Filtramos
            // também pelos IDs dos logs quando possível para não afetar jobs Google/outros.
            if ($logIds !== [] && \Illuminate\Support\Facades\Schema::hasTable('jobs')) {
                $query = DB::table('jobs')->where('payload', 'like', '%SendWhatsappNotificationJob%');
                $query->where(function ($q) use ($logIds): void {
                    foreach ($logIds as $id) {
                        $q->orWhere('payload', 'like', '%'.$id.'%');
                    }
                });
                $deletedJobs = $query->delete();
            }

            foreach ($logs as $log) {
                $log->update([
                    'status' => 'cancelled',
                    'error' => 'Cancelado manualmente pelo Administrador ao limpar a fila.',
                    'sent_at' => null,
                ]);
            }
        });

        return back()->with('status', 'Fila do WhatsApp limpa: '.$logs->count().' aviso(s) cancelado(s) e '.$deletedJobs.' job(s) removido(s). O histórico enviado/falho foi preservado.');
    }

    /**
     * RC93: remove do painel os registros que não foram entregues, sem reenviá-los.
     * Mensagens com status sent são preservadas como histórico de entrega. Jobs ainda
     * associados a queued/retrying não entram aqui; devem ser cancelados pela limpeza
     * da fila para evitar condição de corrida com o worker.
     */
    public function purgeUnsentHistory(Request $request)
    {
        $data = $request->validate([
            'confirmation' => ['required', 'in:REMOVER NAO ENVIADOS'],
        ]);

        $organizationId = (string) $request->user()->organization_id;

        $pending = WhatsappDeliveryLog::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereIn('status', ['queued', 'retrying'])
            ->count();

        if ($pending > 0) {
            return back()->with('error', 'Ainda existem '.$pending.' aviso(s) pendente(s). Use primeiro “Limpar fila pendente” para cancelar os jobs em segurança.');
        }

        $deleted = WhatsappDeliveryLog::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereIn('status', ['failed', 'cancelled', 'skipped'])
            ->delete();

        return back()->with('status', $deleted.' registro(s) não enviado(s) removido(s) do histórico. Mensagens ENVIADAS foram preservadas.');
    }

}
