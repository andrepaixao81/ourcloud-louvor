<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$compiler = $app->make('blade.compiler');
$tmpDir = sys_get_temp_dir().'/sgml-blade-validation-'.bin2hex(random_bytes(5));
mkdir($tmpDir, 0700, true);
$errors = [];
$count = 0;

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/resources/views'));
foreach ($iterator as $file) {
    if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
        continue;
    }

    $count++;
    $source = file_get_contents($file->getPathname());
    if ($source === false) {
        $errors[] = 'Falha ao ler '.$file->getPathname();
        continue;
    }

    try {
        $compiled = $compiler->compileString($source);
        $tmp = $tmpDir.'/'.sha1($file->getPathname()).'.php';
        file_put_contents($tmp, $compiled);
        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($tmp).' 2>&1', $output, $status);
        if ($status !== 0) {
            $relative = str_replace($root.'/', '', $file->getPathname());
            $errors[] = $relative.': '.implode(' ', $output);
        }
        $output = [];
    } catch (Throwable $e) {
        $relative = str_replace($root.'/', '', $file->getPathname());
        $errors[] = $relative.': '.$e->getMessage();
    }
}

foreach (glob($tmpDir.'/*') ?: [] as $tmp) {
    @unlink($tmp);
}
@rmdir($tmpDir);

if ($errors !== []) {
    foreach ($errors as $error) {
        echo "[ERRO] {$error}\n";
    }
    echo 'Resultado: FALHA ('.count($errors)." view(s))\n";
    exit(1);
}

echo "[OK] {$count} views Blade compiladas e validadas com php -l.\n";
echo "Resultado: OK\n";
