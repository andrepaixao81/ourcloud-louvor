<?php

$root = dirname(__DIR__);
$failures = 0;
$check = function (bool $ok, string $label) use (&$failures): void {
    echo ($ok ? '[OK] ' : '[ERRO] ').$label.PHP_EOL;
    if (! $ok) $failures++;
};
$read = fn (string $path): string => is_file($root.'/'.$path) ? (string) file_get_contents($root.'/'.$path) : '';

$version = trim($read('VERSION'));
$recurring = $read('app/Services/RecurringServiceGeneratorService.php');
$cleanup = $read('app/Services/OperationalCleanupService.php');
$controller = $read('app/Http/Controllers/AdminOperationalCleanupController.php');
$view = $read('resources/views/admin/operational-cleanup.blade.php');
$routes = $read('routes/web.php');
$admin = $read('resources/views/admin/index.blade.php');

$check(in_array($version, ['1.0.0-rc89', '1.0.0-rc90', '1.0.0-rc91', '1.0.0-rc92', '1.0.0-rc93', '1.0.0-rc94'], true), 'Linha RC89+ identificada.');
$check(str_contains($recurring, 'data + horário original') && str_contains($recurring, '$expectedKeys->contains($storedKey)') && str_contains($recurring, '$service->starts_at->equalTo($canonicalStart)'), 'Recorrência valida chave e instante canônico da ocorrência, não apenas a data.');
$check(str_contains($cleanup, 'DB::table(\'services\')->where(\'organization_id\', $organizationId)') && str_contains($cleanup, 'whereIn(\'id\', $serviceIdsToRemove)') && str_contains($cleanup, 'regenerateRecurringServices') && str_contains($cleanup, '$this->recurring->generateMonth'), 'Limpeza remove materializações recorrentes antigas; recriação é opcional a partir das regras atuais.');
$check(str_contains($cleanup, "deleteOrgTable('schedule_assignments'") && str_contains($cleanup, "deleteOrgTable('swap_requests'") && str_contains($cleanup, "deleteOrgTable('repertoires'") && str_contains($cleanup, "deleteOrgTable('service_executions'"), 'Escalas, trocas, repertórios e execuções de teste entram na limpeza.');
$check(! str_contains($cleanup, "DB::table('songs')->delete") && ! str_contains($cleanup, "DB::table('users')->delete") && ! str_contains($cleanup, "DB::table('rehearsals')->delete") && ! str_contains($cleanup, 'DB::table(\'minister_song_keys\')->where(\'organization_id\', $organizationId)->delete()'), 'Biblioteca, usuários, ensaios e tons lembrados não são apagados pela rotina.');
$check(str_contains($cleanup, "DB::table('recurring_service_rules')") && ! str_contains($cleanup, "DB::table('recurring_service_rules')->delete"), 'Regras dos cultos são apenas contabilizadas/preservadas.');
$check(str_contains($controller, '$backups->create') && str_contains($controller, "'in:LIMPAR TESTES'"), 'Limpeza exige confirmação forte e gera backup automático antes da exclusão.');
$check(str_contains($routes, 'admin/operational-cleanup') && str_contains($routes, "throttle:admin-operational-cleanup"), 'Rota destrutiva é exclusiva do Administrador e usa throttle administrativo independente.');
$check(str_contains($view, 'O que será preservado') && str_contains($view, 'LIMPAR TESTES') && str_contains($view, 'regras atuais dos cultos') && str_contains($view, 'regenerate_recurring_services'), 'UI explica claramente preservação, remoção e confirmação.');
$check(str_contains($admin, 'Limpeza de dados de teste'), 'Administração expõe a nova rotina de manutenção.');

if ($failures > 0) {
    echo "Resultado: FALHA ({$failures} teste(s))".PHP_EOL;
    exit(1);
}

echo 'Resultado: OK'.PHP_EOL;
