<?php

$root = dirname(__DIR__);
$failures = 0;

$check = function (bool $ok, string $label) use (&$failures): void {
    echo ($ok ? '[OK] ' : '[ERRO] ').$label.PHP_EOL;
    if (! $ok) $failures++;
};

$read = fn (string $path): string => is_file($root.'/'.$path) ? (string) file_get_contents($root.'/'.$path) : '';

$version = trim($read('VERSION'));
$composer = json_decode($read('composer.json'), true) ?: [];
$license = $read('LICENSE');
$readme = $read('README.md');
$envExample = $read('.env.example');
$install = $read('deploy/install.sh');
$workflow = $read('.github/workflows/quality.yml');

$check($version === '1.0.0-rc88', 'Versão RC88 identificada.');
$check(($composer['name'] ?? null) === 'ourcloud/sgml', 'composer.json preserva o identificador do pacote SGML.');
$check(($composer['license'] ?? null) === 'AGPL-3.0-only', 'composer.json declara AGPL-3.0-only.');
$check(str_contains($license, 'GNU AFFERO GENERAL PUBLIC LICENSE') && str_contains($license, 'Version 3, 19 November 2007'), 'LICENSE contém AGPL v3.');
$check(str_contains($readme, 'Instalação rápida') && str_contains($readme, 'deploy/install.sh'), 'README contém instalação passo a passo.');
$check(str_contains($readme, 'AGPL-3.0'), 'README documenta a licença open source.');
$check(is_file($root.'/.env.example') && preg_match('/^APP_KEY=\s*$/m', $envExample) === 1, '.env.example existe sem APP_KEY real.');
$check(is_file($root.'/.github/workflows/quality.yml') && str_contains($workflow, 'php artisan test'), 'GitHub Actions executa testes Laravel.');
$check(is_file($root.'/CONTRIBUTING.md') && is_file($root.'/CODE_OF_CONDUCT.md') && is_file($root.'/PRIVACY.md'), 'Documentação comunitária presente.');
$check(is_file($root.'/database/data/example_song_catalog.php') && ! is_file($root.'/database/data/apascentar_song_catalog.php'), 'Catálogo embutido foi generalizado para exemplos fictícios.');
$check(str_contains($install, 'openssl rand -hex 16') && ! str_contains($install, "tr -dc 'A-Za-z0-9@#%+=_-' </dev/urandom | head -c 20"), 'Gerador automático de senha não depende de pipeline incompatível com pipefail.');

$scopedFiles = [
    'app/Services/SongImportService.php',
    'app/Http/Controllers/SongController.php',
    'resources/views/songs/import.blade.php',
    'docs/catalogo_louvores_exemplo.csv',
];
$hasPrivateCatalogBrand = false;
foreach ($scopedFiles as $file) {
    if (stripos($read($file), 'apascentar') !== false) $hasPrivateCatalogBrand = true;
}
$check(! $hasPrivateCatalogBrand, 'Fluxo público de importação não depende de catálogo de uma igreja específica.');

if ($failures > 0) {
    echo "Resultado: FALHA ({$failures} teste(s))".PHP_EOL;
    exit(1);
}

echo 'Resultado: OK'.PHP_EOL;
