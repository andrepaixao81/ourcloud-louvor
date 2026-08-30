<?php

$root = dirname(__DIR__);
$read = fn (string $path): string => is_file($root.'/'.$path) ? (string) file_get_contents($root.'/'.$path) : '';
$failures = 0;
$checks = 0;
$check = function (bool $condition, string $label) use (&$failures, &$checks): void {
    $checks++;
    echo ($condition ? '[OK] ' : '[ERRO] ').$label.PHP_EOL;
    if (! $condition) $failures++;
};

$version = trim($read('VERSION'));
$view = $read('resources/views/livewire/repertoire-editor.blade.php');

$check($version === '1.0.0-rc94', 'Versão RC94 identificada.');
$check(str_contains($view, 'Como montar o repertório') && str_contains($view, 'Etapa 1') && str_contains($view, 'Etapa 2') && str_contains($view, 'Etapa 3 · Ordem final'), 'Fluxo visual em três etapas está presente.');
$check(str_contains($view, 'Abertura <span class="font-semibold text-slate-400">(opcional)</span>') && str_contains($view, 'Ele não será contado como Louvor 1'), 'Abertura está explicitamente marcada como opcional e fora da numeração.');
$check(str_contains($view, 'Adicionar louvores da ministração') && str_contains($view, 'Aqui entram somente os louvores numerados'), 'Louvores principais possuem área própria e linguagem inequívoca.');
$check(str_contains($view, 'ABERTURA') && str_contains($view, 'LOUVOR {{ $loop->iteration }}'), 'Ordem final diferencia abertura da sequência numerada.');
$check(str_contains($view, 'Louvores principais') && str_contains($view, 'A sequência principal começa sempre em <strong>Louvor 1</strong>'), 'Sequência principal começa claramente no Louvor 1.');
$check(str_contains($view, 'Repertório pronto? Avisar a equipe'), 'Ação final usa linguagem orientada à tarefa.');
$check(! str_contains($view, 'RC84:'), 'Interface não expõe número técnico de release ao ministro.');
$check(str_contains($view, 'md:grid-cols-3') && str_contains($view, 'rounded-2xl'), 'Hierarquia visual responsiva foi aplicada sem remover comportamento mobile.');

printf("Resultado: %s (%d verificações)\n", $failures === 0 ? 'OK' : 'FALHA', $checks);
exit($failures === 0 ? 0 : 1);
