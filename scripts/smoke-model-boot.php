<?php

declare(strict_types=1);

use App\Models\BaseModel;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$modelsDir = __DIR__.'/../app/Models';
$errors = [];
$count = 0;

foreach (glob($modelsDir.'/*.php') ?: [] as $file) {
    $class = 'App\\Models\\'.basename($file, '.php');
    if (!class_exists($class) || $class === BaseModel::class || !is_subclass_of($class, BaseModel::class)) {
        continue;
    }

    $reflection = new ReflectionClass($class);
    if ($reflection->isAbstract()) {
        continue;
    }

    try {
        new $class();
        $count++;
        echo "[OK] {$class}\n";
    } catch (Throwable $e) {
        $errors[] = "{$class}: {$e->getMessage()}";
        echo "[ERRO] {$class}: {$e->getMessage()}\n";
    }
}

if ($errors !== []) {
    echo sprintf("Resultado: FALHA (%d model(s) com erro de boot)\n", count($errors));
    exit(1);
}

echo "Resultado: OK ({$count} models BaseModel inicializados)\n";
