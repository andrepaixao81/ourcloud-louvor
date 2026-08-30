<?php

namespace App\Jobs;

use App\Models\ExternalCalendarSync;
use App\Services\GoogleCalendarService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncGoogleCalendarEventJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;
    public int $timeout = 120;
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(public string $syncId) {}

    public function handle(GoogleCalendarService $google): void
    {
        $sync = ExternalCalendarSync::query()->withoutGlobalScopes()->find($this->syncId);
        if (!$sync || $sync->sync_status === 'disabled') return;
        $sync->update(['sync_status'=>'syncing']);
        try {
            $google->sync($sync);
        } catch (Throwable $e) {
            $sync->increment('retry_count');
            $sync->update(['sync_status'=>'failed','last_error'=>mb_substr($this->sanitize($e->getMessage()),0,1500)]);
            throw $e;
        }
    }

    private function sanitize(string $message): string
    {
        return preg_replace('/(access_token|refresh_token|client_secret)\s*[:=]\s*[^\s,}]+/i', '$1=[REDACTED]', $message) ?? 'Erro de sincronização';
    }
}
