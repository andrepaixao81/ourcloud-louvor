<?php

declare(strict_types=1);

use App\Models\Service;
use App\Models\User;
use App\Services\MonthlyScheduleTemplateService;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$email = $argv[1] ?? 'admin@example.org';
$monthValue = $argv[2] ?? now()->format('Y-m');

try {
    $user = User::query()->where('email', $email)->firstOrFail();
    Auth::login($user);

    // Em uma requisição web o middleware ShareErrorsFromSession injeta $errors
    // nas views. O smoke CLI precisa reproduzir esse contexto explicitamente.
    View::share('errors', new ViewErrorBag());

    $request = Request::create('/services/month-template', 'GET', ['month' => $monthValue]);
    $request->setUserResolver(fn () => $user);
    $app->instance('request', $request);

    $month = Carbon::createFromFormat('Y-m', $monthValue)->startOfMonth();
    $template = app(MonthlyScheduleTemplateService::class);

    $candidates = $template->candidates($month);
    if ($candidates->isEmpty()) {
        $sampleMonthService = Service::query()
            ->where('origin', 'manual')
            ->where('status', '!=', 'cancelled')
            ->orderByDesc('starts_at')
            ->first();
        if ($sampleMonthService) {
            $month = $sampleMonthService->starts_at->copy()->startOfMonth();
            $candidates = $template->candidates($month);
            $request = Request::create('/services/month-template', 'GET', ['month' => $month->format('Y-m')]);
            $request->setUserResolver(fn () => $user);
            $app->instance('request', $request);
        }
    }

    view('services.month-template', [
        'month' => $month,
        'candidates' => $candidates,
    ])->render();

    $reflection = new ReflectionMethod(MonthlyScheduleTemplateService::class, 'createRuleFromService');
    $parameter = $reflection->getParameters()[3] ?? null;
    if (! $parameter || (string) $parameter->getType() !== 'string') {
        throw new RuntimeException('createRuleFromService não aceita UUID textual em createdBy.');
    }

    $candidate = $candidates->first();
    if ($candidate) {
        $service = Service::query()->findOrFail($candidate['sample_id']);
        DB::beginTransaction();
        try {
            $rule = $template->createRuleFromService(
                $service,
                'weekly',
                $month->copy()->addMonth()->endOfMonth(),
                (string) $user->id,
            );
            if (! $rule) {
                throw new RuntimeException('Regra de teste não foi criada/localizada.');
            }
        } finally {
            DB::rollBack();
        }
    }

    echo '[OK] Validação mensal renderizada com contexto web; UUID do usuário aceito; transação de regra testada e revertida.'.PHP_EOL;
    echo 'Resultado: OK'.PHP_EOL;
} catch (Throwable $e) {
    echo '[ERRO] '.$e->getMessage().PHP_EOL;
    echo 'Resultado: FALHA'.PHP_EOL;
    exit(1);
}
