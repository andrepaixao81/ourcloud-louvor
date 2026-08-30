<?php

declare(strict_types=1);

use App\Http\Controllers\ServiceController;
use App\Models\Service;
use App\Models\User;
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
    fwrite(STDERR, "Uso: php8.3 scripts/smoke-service-edit.php admin@dominio\n");
    exit(2);
}

$user = User::query()->where('email', $email)->firstOrFail();
Auth::login($user);
View::share('errors', new ViewErrorBag());

$service = Service::query()->orderBy('starts_at')->first();
if (! $service) {
    echo "[AVISO] Nenhum culto disponível para testar a tela de edição.\n";
    exit(0);
}

$request = Request::create('/services/'.$service->id.'/edit', 'GET');
$request->setUserResolver(fn () => $user);
$app->instance('request', $request);

try {
    $view = $app->make(ServiceController::class)->edit($service);
    $html = $view->render();
    if (! str_contains($html, 'Editar este culto') || ! str_contains($html, 'Salvar somente este culto')) {
        throw new RuntimeException('A view de edição foi renderizada, mas não contém os controles RC17 esperados.');
    }
    echo "[OK] Edição de culto renderizada para {$service->id} ({$service->starts_at->format('d/m/Y H:i')}).\n";
    echo "Resultado: OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[ERRO] Edição de culto: '.$e->getMessage()."\n");
    exit(1);
}
