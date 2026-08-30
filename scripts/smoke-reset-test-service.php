<?php

$root = dirname(__DIR__);
$fail = 0;
$check = static function (bool $ok, string $label) use (&$fail): void {
    echo ($ok ? '[OK] ' : '[ERRO] ').$label.PHP_EOL;
    if (! $ok) $fail++;
};

$controller = file_get_contents($root.'/app/Http/Controllers/ServiceController.php') ?: '';
$routes = file_get_contents($root.'/routes/web.php') ?: '';
$view = file_get_contents($root.'/resources/views/services/show.blade.php') ?: '';
$generator = file_get_contents($root.'/app/Services/ScheduleGeneratorService.php') ?: '';
$history = file_get_contents($root.'/app/Services/ServiceHistorySyncService.php') ?: '';
$update = file_get_contents($root.'/deploy/update.sh') ?: '';
$install = file_get_contents($root.'/deploy/install.sh') ?: '';

$check(str_contains($routes, "services/{service}/reset-test-data") && str_contains($routes, "services.reset-test-data"), 'Rota administrativa para zerar dados de teste está registrada.');
$check(str_contains($controller, 'function resetTestData'), 'ServiceController possui ação dedicada de reset de teste.');
$check(str_contains($controller, "isAdministrator() || \$request->user()->isMinistryManager()"), 'Reset de teste é restrito a Administrador/Responsável pelo Ministério.');
$check(str_contains($controller, "Rule::in(['ZERAR'])") && str_contains($controller, "'acknowledge' => ['accepted']"), 'Reset exige confirmação explícita ZERAR e ciência da exclusão.');
$check(str_contains($controller, "ServiceExecution::query()->where('service_id', \$service->id)") && str_contains($controller, '$execution->delete();'), 'Execução do culto de teste é removida fisicamente.');
$check(str_contains($controller, "SongExecution::query()->withTrashed()") && str_contains($controller, "song_executions_removed"), 'Reset contabiliza e elimina inclusive registros de Louvores ministrados.');
$check(str_contains($controller, "Repertoire::query()->withTrashed()->where('service_id', \$service->id)") && str_contains($controller, '$repertoire->forceDelete();'), 'Todas as versões de repertório do teste são removidas.');
$check(str_contains($controller, "ScheduleAssignment::query()") && str_contains($controller, "where('service_id', \$service->id)") && str_contains($controller, '$assignment->forceDelete();'), 'Atribuição de escala é removida para o culto voltar ao gerador.');
$check(str_contains($controller, "ServiceAdditionalMinister::query()") && str_contains($controller, "CalendarFeedState::query()"), 'Ministros adicionais e estado de feed de teste são limpos.');
$check(str_contains($controller, "'status' => 'scheduled'"), 'Culto retorna ao status Agendado após o reset.');
$check(str_contains($controller, "service.test_data.reset"), 'A limpeza destrutiva permanece registrada na auditoria.');
$check(str_contains($view, 'Zerar dados de teste deste culto') && str_contains($view, 'Louvores ministrados'), 'Tela explica claramente que o histórico ministrado será removido.');
$check(str_contains($view, "route('services.reset-test-data', \$service)"), 'Tela envia a ação para a rota dedicada.');
$check(str_contains($generator, "whereNotIn('status', ['cancelled', 'completed', 'in_progress'])"), 'Gerador aceita novamente o culto após retornar a scheduled.');
$check(str_contains($history, "whereHas('repertoire')"), 'Sem repertório de teste, finalizador automático não reconclui imediatamente o culto apenas pelo reset.');
$check(str_contains($update, 'smoke-reset-test-service.php'), 'Atualizador valida a limpeza de culto de teste.');
$check(str_contains($install, 'smoke-reset-test-service.php'), 'Instalador valida a limpeza de culto de teste.');

if ($fail) {
    echo "Resultado: FALHA ($fail teste(s))".PHP_EOL;
    exit(1);
}

echo "Resultado: OK".PHP_EOL;
