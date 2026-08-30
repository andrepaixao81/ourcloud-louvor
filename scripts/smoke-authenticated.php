<?php

declare(strict_types=1);

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdminMinisterEligibilityController;
use App\Http\Controllers\AdministrationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatabaseBackupController;
use App\Http\Controllers\MailSettingsController;
use App\Http\Controllers\PeopleController;
use App\Http\Controllers\RehearsalController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SongController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ServiceTypeController;
use App\Http\Controllers\SwapController;
use App\Http\Controllers\SpecialEventController;
use App\Http\Controllers\WhatsappSettingsController;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\DashboardService;
use App\Services\DatabaseBackupService;
use App\Services\MonthlyScheduleTemplateService;
use App\Services\OrganizationMailSettingsService;
use App\Services\OrganizationGoogleCalendarSettingsService;
use App\Services\MinisterAssistanceContext;
use App\Services\InternalCalendarService;
use App\Services\ScheduleValidationService;
use App\Services\ServiceHistorySyncService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$email = $argv[1] ?? '';
if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Uso: php8.3 scripts/smoke-authenticated.php admin@dominio\n");
    exit(2);
}

$user = User::query()->where('email', $email)->firstOrFail();
Auth::login($user);
View::share('errors', new ViewErrorBag());

$withRequest = function (string $uri, array $query, callable $callback) use ($app): void {
    $request = Request::create($uri, 'GET', $query);
    $request->setUserResolver(fn () => Auth::user());
    $app->instance('request', $request);

    $result = $callback($request);

    if ($result instanceof \Illuminate\Contracts\View\View) {
        $result->render();
        return;
    }
    if ($result instanceof \Illuminate\Http\Response) {
        $result->getContent();
        return;
    }
    if (is_object($result) && method_exists($result, 'render')) {
        $result->render();
    }
};

$tests = [
    'dashboard' => function () use ($app, $withRequest): void {
        $withRequest('/', [], fn (Request $request) => $app->make(DashboardController::class)($request, $app->make(DashboardService::class)));
    },
    'my-agenda' => function () use ($app, $withRequest): void {
        $withRequest('/my-agenda', [], fn (Request $request) => $app->make(\App\Http\Controllers\CalendarController::class)->myAgenda(
            $request,
            $app->make(AvailabilityService::class),
            $app->make(OrganizationGoogleCalendarSettingsService::class),
            $app->make(MinisterAssistanceContext::class)
        ));
    },
    'calendar' => function () use ($app, $withRequest): void {
        $withRequest('/calendar', ['view' => 'month', 'date' => now()->format('Y-m-d')], fn (Request $request) => $app->make(\App\Http\Controllers\CalendarController::class)->index(
            $request,
            $app->make(InternalCalendarService::class)
        ));
    },
    'calendar-sync' => function () use ($app, $withRequest): void {
        $withRequest('/calendar/sync', [], function (Request $request) use ($app) {
            $view = $app->make(\App\Http\Controllers\CalendarController::class)->sync(
                $request,
                $app->make(MinisterAssistanceContext::class)
            );
            $html = $view->render();
            if (! str_contains($html, 'data-calendar-subscribe')
                || ! str_contains($html, 'data-webcal-url=')
                || ! str_contains($html, 'data-https-url=')
                || ! str_contains($html, 'webcal://')) {
                throw new RuntimeException('Tela de sincronização não renderizou o acionador móvel WebCal/HTTPS.');
            }
            return null;
        });
    },
    'organization-structure' => function () use ($app, $withRequest): void {
        $withRequest('/congregations', [], fn () => $app->make(\App\Http\Controllers\CongregationController::class)->index());
    },
    'admin-catalogs' => function () use ($app, $withRequest): void {
        $withRequest('/admin/catalogs', [], fn () => $app->make(\App\Http\Controllers\AdminCatalogController::class)->index());
    },
    'schedule' => function () use ($app, $withRequest): void {
        $withRequest('/schedule', ['month' => now()->format('Y-m')], fn (Request $request) => $app->make(ScheduleController::class)->index($request, $app->make(ScheduleValidationService::class), $app->make(AvailabilityService::class)));
    },
    'services-calendar' => function () use ($app, $withRequest): void {
        $withRequest('/services', ['month' => now()->format('Y-m')], fn (Request $request) => $app->make(ServiceController::class)->index($request));
    },
    'service-detail' => function () use ($app, $withRequest): void {
        $service = \App\Models\Service::query()->orderBy('starts_at')->first();
        if (! $service) return;
        $returnTo = '/services?month='.$service->starts_at->format('Y-m').'&view=calendar';
        $withRequest('/services/'.$service->id, ['return_to' => $returnTo], fn (Request $request) => $app->make(ServiceController::class)->show($request, $service));
    },
    'calendar-service-detail' => function () use ($app, $withRequest): void {
        $service = \App\Models\Service::query()->where('status', '!=', 'cancelled')->orderBy('starts_at')->first();
        if (! $service) return;
        $withRequest('/calendar/services/'.$service->id, [], fn () => $app->make(\App\Http\Controllers\CalendarController::class)->serviceDetail($service));
    },
    'service-edit' => function () use ($app, $withRequest): void {
        $service = \App\Models\Service::query()->orderBy('starts_at')->first();
        if (! $service) {
            return;
        }
        $withRequest('/services/'.$service->id.'/edit', [], fn () => $app->make(ServiceController::class)->edit($service));
    },
    'services-month-template' => function () use ($app, $withRequest): void {
        $withRequest('/services/month-template', ['month' => now()->format('Y-m')], fn (Request $request) => $app->make(ServiceController::class)->monthTemplate($request, $app->make(MonthlyScheduleTemplateService::class)));
    },
    'recurrence-rules' => function () use ($app, $withRequest): void {
        $withRequest('/services/recurring', [], fn (Request $request) => $app->make(ServiceController::class)->recurringRules($request));
    },
    'songs' => function () use ($app, $withRequest): void {
        $withRequest('/songs', ['q' => '', 'category_id' => ''], fn (Request $request) => $app->make(SongController::class)->index($request));
    },
    'rehearsals' => function () use ($app, $withRequest): void {
        $withRequest('/rehearsals', ['status' => 'practicing'], fn (Request $request) => $app->make(RehearsalController::class)->index($request));
    },
    'song-history' => function () use ($app, $withRequest): void {
        $withRequest('/song-history', [], fn (Request $request) => $app->make(\App\Http\Controllers\SongHistoryController::class)->index($request, $app->make(ServiceHistorySyncService::class)));
    },
    'songs-import' => function () use ($app, $withRequest): void {
        $withRequest('/songs/import', [], fn () => $app->make(SongController::class)->importForm());
    },
    'service-types' => function () use ($app, $withRequest): void {
        $withRequest('/service-types', [], fn () => $app->make(ServiceTypeController::class)->index());
    },
    'people' => function () use ($app, $withRequest): void {
        $withRequest('/people', [], fn () => $app->make(PeopleController::class)->index());
    },
    'reports' => function () use ($app, $withRequest): void {
        $withRequest('/reports', [], fn (Request $request) => $app->make(\App\Http\Controllers\ReportController::class)->index($request));
    },
    'swaps' => function () use ($app, $withRequest): void {
        $withRequest('/swaps', [], fn (Request $request) => $app->make(SwapController::class)->index(
            $request,
            $app->make(AvailabilityService::class)
        ));
    },
    'special-events' => function () use ($app, $withRequest): void {
        $withRequest('/special-events', ['month' => now()->format('Y-m')], fn (Request $request) => $app->make(SpecialEventController::class)->index($request));
    },
    'report-song-detail' => function () use ($app, $withRequest): void {
        $songId = \App\Models\SongExecution::query()->whereNotNull('song_id')->value('song_id');
        if (! $songId) return;
        $withRequest('/reports/songs/'.$songId, [], fn (Request $request) => $app->make(\App\Http\Controllers\ReportController::class)->song($request, (string) $songId));
    },
    'administration' => function () use ($app, $withRequest): void {
        $withRequest('/admin', [], fn () => $app->make(AdministrationController::class)->index($app->make(MinisterAssistanceContext::class)));
    },
    'admin-users' => function () use ($app, $withRequest): void {
        $withRequest('/admin/users', [], fn (Request $request) => $app->make(AdminUserController::class)->index($request, $app->make(OrganizationMailSettingsService::class)));
    },
    'admin-groups' => function () use ($app, $withRequest): void {
        $withRequest('/admin/groups', [], fn (Request $request) => $app->make(AdminUserController::class)->groups($request));
    },
    'admin-minister-eligibility' => function () use ($app, $withRequest): void {
        $withRequest('/admin/minister-eligibility', [], fn (Request $request) => $app->make(AdminMinisterEligibilityController::class)->index($request));
    },
    'audit' => function () use ($app, $withRequest): void {
        $withRequest('/audit', [], fn (Request $request) => $app->make(\App\Http\Controllers\AuditController::class)->index($request));
    },
    'admin-mail' => function () use ($app, $withRequest): void {
        $withRequest('/admin/mail', [], fn (Request $request) => $app->make(MailSettingsController::class)->index($request, $app->make(OrganizationMailSettingsService::class)));
    },
    'admin-backups' => function () use ($app, $withRequest): void {
        $withRequest('/admin/backups', [], fn (Request $request) => $app->make(DatabaseBackupController::class)->index($request, $app->make(DatabaseBackupService::class)));
    },
    'admin-whatsapp' => function () use ($app, $withRequest): void {
        $withRequest('/admin/whatsapp', [], fn (Request $request) => $app->make(WhatsappSettingsController::class)->index($request));
    },
];

$failures = [];
foreach ($tests as $name => $test) {
    try {
        $test();
        echo "[OK] {$name}\n";
    } catch (Throwable $e) {
        $failures[] = $name.': '.get_class($e).' - '.$e->getMessage();
        echo "[ERRO] {$name}: ".get_class($e).' - '.$e->getMessage()."\n";
    }
}

if ($failures !== []) {
    echo 'Resultado: FALHA ('.count($failures)." teste(s))\n";
    exit(1);
}

echo "Resultado: OK\n";
