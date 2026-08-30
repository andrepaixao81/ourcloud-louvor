<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [];
$failures = [];
$assert = function (bool $condition, string $label) use (&$checks, &$failures): void {
    $checks[] = [$condition, $label];
    if (! $condition) $failures[] = $label;
};
$read = static fn (string $relative): string => (string) file_get_contents($root.'/'.$relative);

$controller = $read('app/Http/Controllers/SpecialEventController.php');
$routes = $read('routes/web.php');
$show = $read('resources/views/special-events/show.blade.php');
$form = $read('resources/views/special-events/form.blade.php');

$assert(str_contains($routes, "special-events/{specialEvent}/edit"), 'Rota de edição de evento especial registrada.');
$assert(str_contains($routes, "name('special-events.update')"), 'Rota de atualização de evento especial registrada.');
$assert(str_contains($routes, "name('special-events.destroy')"), 'Rota de exclusão de evento especial registrada.');
$assert(str_contains($controller, 'public function edit('), 'Controller oferece edição de evento especial.');
$assert(str_contains($controller, 'public function update('), 'Controller atualiza evento e culto vinculado.');
$assert(str_contains($controller, "special_event.updated"), 'Edição é auditada.');
$assert(str_contains($controller, 'deleteServiceProjection'), 'Cancelamento/exclusão remove projeção ativa da agenda.');
$assert(str_contains($controller, 'public function destroy('), 'Controller oferece exclusão controlada de evento especial.');
$assert(str_contains($controller, "confirmation' => ['required', 'in:EXCLUIR']"), 'Exclusão exige confirmação textual EXCLUIR.');
$assert(str_contains($controller, 'Este evento já possui histórico efetivo'), 'Exclusão é bloqueada quando existe histórico efetivo.');
$assert(str_contains($controller, "special_event.deleted"), 'Exclusão preserva auditoria.');
$assert(str_contains($show, 'Editar evento'), 'Tela exibe ação Editar evento.');
$assert(str_contains($show, 'Cancelar evento'), 'Tela exibe ação Cancelar evento.');
$assert(str_contains($show, 'Excluir evento'), 'Tela exibe ação Excluir evento.');
$assert(str_contains($show, 'sairá da agenda e da escala ativa'), 'Tela explica o efeito operacional do cancelamento.');
$assert(str_contains($form, 'Salvar alterações e avisar equipe'), 'Formulário reutilizado para edição com aviso da equipe.');

foreach ($checks as [$ok, $label]) echo ($ok ? '[OK] ' : '[ERRO] ').$label."\n";
if ($failures !== []) {
    echo 'Resultado: FALHA ('.count($failures)." verificação(ões))\n";
    exit(1);
}
echo "Resultado: OK\n";
