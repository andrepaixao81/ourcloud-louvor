<?php
$root = dirname(__DIR__);
$checks = [
    'versão RC85' => in_array(trim(file_get_contents($root.'/VERSION')), ['1.0.0-rc85', '1.0.0-rc86', '1.0.0-rc87', '1.0.0-rc88', '1.0.0-rc89', '1.0.0-rc90', '1.0.0-rc91', '1.0.0-rc92', '1.0.0-rc93', '1.0.0-rc94'], true),
    'view 403 amigável' => is_file($root.'/resources/views/errors/403.blade.php'),
    '403 contextual Central de Ações' => str_contains(file_get_contents($root.'/resources/views/errors/403.blade.php'), 'action_item_context'),
    '403 permite dispensar aviso' => str_contains(file_get_contents($root.'/resources/views/errors/403.blade.php'), "route('actions.dismiss'"),
    'rota segura de abertura' => str_contains(file_get_contents($root.'/routes/web.php'), "name('actions.open')"),
    'controller mantém contexto' => str_contains(file_get_contents($root.'/app/Http/Controllers/ActionCenterController.php'), "session()->flash('action_item_context'"),
    'central usa actions.open' => str_contains(file_get_contents($root.'/resources/views/actions/index.blade.php'), "route('actions.open', \$item)"),
    'modal respeita hidden' => str_contains(file_get_contents($root.'/resources/css/app.css'), '.sgml-dialog-backdrop.hidden'),
];
$failed = [];
foreach ($checks as $label => $ok) {
    echo ($ok ? '[OK] ' : '[ERRO] ').$label.PHP_EOL;
    if (!$ok) $failed[] = $label;
}
exit($failed ? 1 : 0);
