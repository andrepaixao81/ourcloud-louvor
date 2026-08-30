<?php

$root = dirname(__DIR__);
$checks = 0;
$failed = 0;
$check = function (bool $ok, string $message) use (&$checks, &$failed): void {
    $checks++;
    echo ($ok ? '[OK] ' : '[FALHA] ').$message.PHP_EOL;
    if (! $ok) $failed++;
};

$read = fn (string $path): string => file_get_contents($root.'/'.$path) ?: '';
$layout = $read('resources/views/layouts/app.blade.php');
$css = $read('resources/css/app.css');
$js = $read('resources/js/app.js');
$agenda = $read('resources/views/calendar/my-agenda.blade.php');
$calendar = $read('resources/views/calendar/index.blade.php');
$programmed = $read('resources/views/calendar/programmed-songs.blade.php');
$availability = $read('resources/views/calendar/availability.blade.php');
$dashboard = $read('resources/views/dashboard/index.blade.php');
$actions = $read('resources/views/actions/index.blade.php');
$people = $read('resources/views/people/form.blade.php');
$services = $read('resources/views/services/form.blade.php');
$adminUsers = $read('resources/views/admin/users.blade.php');
$views = '';
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/resources/views')) as $file) {
    if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) $views .= file_get_contents($file->getPathname()) ?: '';
}

$check(str_contains($layout, 'sgml-sidebar') && str_contains($layout, '>Ministério</div>') && str_contains($layout, '>Planejamento</div>'), 'Admin/Líder usam navegação lateral agrupada no desktop.');
$check(str_contains($layout, '<span>Agenda</span>') && str_contains($layout, '<span>Louvores</span>') && str_contains($layout, '<span>Ações</span>') && str_contains($layout, '<span>Conta</span>'), 'Ministro possui navegação inferior mobile por tarefas principais.');
$check(str_contains($agenda, 'Seu próximo compromisso') && str_contains($agenda, 'Precisa da sua atenção') && str_contains($agenda, 'Próximos compromissos'), 'Minha Agenda prioriza próximo compromisso, pendências e linha temporal.');
$check(str_contains($calendar, 'data-calendar-auto-view') && str_contains($calendar, 'Lista') && str_contains($calendar, 'Semana') && str_contains($calendar, 'Mês'), 'Agenda oferece visões temporais e abre lista automaticamente no celular.');
$check(str_contains($programmed, 'Copiar repertório') && str_contains($programmed, 'data-copy-text') && str_contains($programmed, 'Ministro(s):'), 'Louvores programados possuem cartões operacionais e cópia rápida de repertório.');
$check(str_contains($availability, 'Quando você normalmente pode ministrar?') && str_contains($availability, 'Exceções') && str_contains($availability, '<details'), 'Disponibilidade foi reorganizada em rotina, exceções e opções avançadas.');
$check(str_contains($dashboard, 'Atenção necessária') && str_contains($dashboard, 'sem ministro') && str_contains($dashboard, 'sem repertório'), 'Dashboard destaca pendências acionáveis antes dos indicadores.');
$check(str_contains($actions, 'Central de ações') || str_contains($actions, 'Ações'), 'Centro de Ações usa apresentação de caixa de entrada operacional.');
$check(str_contains($people, 'Informações básicas') && str_contains($people, 'Participação no ministério') && str_contains($people, 'Gerenciar acesso'), 'Cadastro de Pessoas separa dados, participação e acesso.');
$check(str_contains($services, 'Informações básicas') && str_contains($services, 'Ministério e escala') && str_contains($services, 'Detalhes complementares'), 'Formulário de culto separa conteúdo essencial e complementar.');
$check(str_contains($adminUsers, 'Perfis de acesso') && str_contains($adminUsers, 'Permissões específicas'), 'Administração traduz RBAC/ACL para linguagem de produto.');
$check(str_contains($js, 'data-confirm-dialog') && str_contains($js, 'confirmReasonLabel') && ! str_contains($views, 'return confirm(') && ! str_contains($views, 'prompt('), 'Confirmações críticas usam modal próprio, inclusive justificativas.');
$check(str_contains($js, 'data-page-loader') && str_contains($css, 'sgml-loading-skeleton'), 'Navegação possui feedback de carregamento e skeleton.');
$check(str_contains($css, 'sgml-badge-success') && str_contains($css, 'sgml-badge-warning') && str_contains($css, 'sgml-badge-danger'), 'Design system padroniza estados sem depender apenas de cor.');
$check(in_array(trim($read('VERSION')), ['1.0.0-rc83', '1.0.0-rc84', '1.0.0-rc85', '1.0.0-rc86', '1.0.0-rc87', '1.0.0-rc88'], true), 'Versão da distribuição preserva a base RC83 de UI/UX.');

if ($failed) {
    fwrite(STDERR, "Resultado: FALHA ($failed de $checks)\n");
    exit(1);
}

echo "Resultado: OK ($checks verificações)\n";
