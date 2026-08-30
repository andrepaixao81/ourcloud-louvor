<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Paginator::useTailwind();
        Blade::if('perm', fn (string $permission) => auth()->check() && auth()->user()->hasPermission($permission));

        // RC92: limites administrativos independentes por operação. Os throttles
        // genéricos do Laravel podem compartilhar a assinatura do usuário/domínio e
        // fazer uma ação bloquear outra (ex.: testar WhatsApp -> limpar fila -> 429).
        RateLimiter::for('admin-operational-cleanup', fn (Request $request) =>
            Limit::perMinute(3)->by(($request->user()?->getAuthIdentifier() ?? $request->ip()).'|operational-cleanup')
        );
        RateLimiter::for('admin-whatsapp', fn (Request $request) =>
            Limit::perMinute(30)->by(($request->user()?->getAuthIdentifier() ?? $request->ip()).'|whatsapp-admin')
        );
        RateLimiter::for('admin-user-removal', fn (Request $request) =>
            Limit::perMinute(10)->by(($request->user()?->getAuthIdentifier() ?? $request->ip()).'|user-removal')
        );
        RateLimiter::for('admin-backup-write', fn (Request $request) =>
            Limit::perMinute(10)->by(($request->user()?->getAuthIdentifier() ?? $request->ip()).'|backup-write')
        );
        RateLimiter::for('admin-backup-restore', fn (Request $request) =>
            Limit::perMinute(5)->by(($request->user()?->getAuthIdentifier() ?? $request->ip()).'|backup-restore')
        );
        RateLimiter::for('admin-mail-test', fn (Request $request) =>
            Limit::perMinute(10)->by(($request->user()?->getAuthIdentifier() ?? $request->ip()).'|mail-test')
        );
    }
}
