<?php

use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\ApplyMinisterAssistance;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([__DIR__.'/../app/Console/Commands'])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => EnsurePermission::class,
            'administrator' => \App\Http\Middleware\EnsureAdministrator::class,
        ]);
        $middleware->append(SecurityHeaders::class);
        $middleware->web(append: [EnsurePasswordChanged::class, ApplyMinisterAssistance::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // O tratamento padrão do Laravel é mantido. Logs não devem conter segredos.
    })->create();
