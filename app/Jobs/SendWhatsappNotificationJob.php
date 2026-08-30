<?php

namespace App\Jobs;

use App\Models\WhatsappDeliveryLog;
use App\Models\WhatsappSetting;
use App\Services\WhatsappGatewayService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class SendWhatsappNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 45;

    public function __construct(public readonly string $deliveryLogId)
    {
    }

    public function handle(WhatsappGatewayService $gateway): void
    {
        $log = WhatsappDeliveryLog::query()->withoutGlobalScopes()->find($this->deliveryLogId);
        if (! $log || $log->status === 'sent') {
            return;
        }

        $settings = WhatsappSetting::query()->withoutGlobalScopes()
            ->where('organization_id', $log->organization_id)
            ->first();

        if (! $settings?->isConfigured()) {
            $log->update(['status' => 'skipped', 'error' => 'Integração WhatsApp desabilitada ou incompleta.']);
            return;
        }

        try {
            $result = $gateway->send($settings, $log);
            $log->update([
                'status' => 'sent',
                'provider' => $result['provider'],
                'provider_message_id' => $result['message_id'],
                'error' => null,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            $log->update([
                'status' => 'failed',
                'provider' => $settings->selectedProvider(),
                'error' => Str::limit($exception->getMessage(), 2000, ''),
            ]);
            throw $exception;
        }
    }
}
