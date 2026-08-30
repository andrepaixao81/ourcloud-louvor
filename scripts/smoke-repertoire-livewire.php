<?php

$root = dirname(__DIR__);
$checks = [];
$fail = 0;

$check = function (bool $ok, string $label) use (&$fail): void {
    echo ($ok ? '[OK] ' : '[ERRO] ').$label.PHP_EOL;
    if (! $ok) $fail++;
};

$component = file_get_contents($root.'/app/Livewire/RepertoireEditor.php') ?: '';
$view = file_get_contents($root.'/resources/views/livewire/repertoire-editor.blade.php') ?: '';
$layout = file_get_contents($root.'/resources/views/layouts/app.blade.php') ?: '';
$appJs = file_get_contents($root.'/resources/js/app.js') ?: '';
$update = file_get_contents($root.'/deploy/update.sh') ?: '';
$install = file_get_contents($root.'/deploy/install.sh') ?: '';

$check(str_contains($appJs, "import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';"), 'Frontend empacota Livewire pelo Vite.');
$check(str_contains($appJs, 'Livewire.start();'), 'Frontend inicia explicitamente o runtime Livewire.');
$check(str_contains($layout, '@livewireScriptConfig'), 'Layout usa @livewireScriptConfig com runtime empacotado.');
$check(! str_contains($layout, '@livewireScripts'), 'Layout não carrega uma segunda cópia dinâmica do Livewire.');
$check(str_contains($component, 'function selectOpeningSong'), 'Componente possui seleção explícita do louvor de abertura.');
$check(str_contains($component, 'function selectSong'), 'Componente possui seleção explícita dos louvores numerados.');
$check(str_contains($view, 'wire:model.live.debounce.300ms="openingSongSearch"'), 'Busca da abertura é reativa.');
$check(str_contains($view, 'wire:model.live.debounce.300ms="songSearch"'), 'Busca dos louvores numerados é reativa.');
$check(str_contains($view, 'Adicionar louvores ao repertório'), 'Bloco de inclusão dos Louvores 1, 2, 3... está visível.');
$check(str_contains($component, 'public bool $songSearchResultsOpen = false;'), 'Resultados da busca de louvores possuem estado explícito de abertura/fechamento.');
$check(str_contains($component, 'public bool $openingSearchResultsOpen = false;'), 'Resultados da busca de abertura possuem estado explícito de abertura/fechamento.');
$check(str_contains($component, '$this->songSearchResultsOpen = false;'), 'Selecionar um louvor fecha a caixa de resultados do repertório.');
$check(str_contains($component, '$this->openingSearchResultsOpen = false;'), 'Selecionar abertura fecha a caixa de resultados de abertura.');
$check(str_contains($view, 'trim($songSearch) !== \'\' && $songSearchResultsOpen'), 'Lista de resultados do repertório só permanece visível enquanto estiver aberta.');
$check(str_contains($view, 'trim($openingSongSearch) !== \'\' && $openingSearchResultsOpen'), 'Lista de resultados da abertura só permanece visível enquanto estiver aberta.');
$check(strpos($view, 'Adicionar louvores ao repertório') < strpos($view, 'Louvores do repertório'), 'Inclusão aparece antes da lista de louvores, no fluxo natural de montagem.');
$check(str_contains($view, 'Adicionar louvor ao repertório'), 'Botão explícito de adicionar louvor está presente.');

$check(str_contains($component, 'function addSelectedSong'), 'Ação explícita addSelectedSong está disponível.');
$check(str_contains($view, 'wire:click="addSelectedSong"'), 'Botão Adicionar usa wire:click explícito em vez de depender de submit do formulário.');
$check(! str_contains($view, 'wire:submit="add"'), 'Fluxo principal não depende mais de wire:submit para adicionar louvor.');
$check(str_contains($view, 'wire:loading') && str_contains($view, 'Adicionando...'), 'Botão informa estado de envio durante a inclusão.');
$check(str_contains($component, "addError('addSong'"), 'Falha de inclusão é exibida ao usuário em vez de parecer botão inerte.');
$check(str_contains(file_get_contents($root.'/app/Services/RepertoireService.php') ?: '', 'Uma falha transitória de projeção'), 'Falha secundária de projeção não invalida a persistência principal do repertório.');
$check(str_contains($view, 'Cadastrar e adicionar ao repertório'), 'Cadastro rápido de música ausente permanece disponível.');
$check(str_contains($view, 'Confirmar repertório e avisar equipe'), 'Confirmação final do repertório permanece disponível.');
$check(str_contains($update, 'smoke-repertoire-livewire.php'), 'Atualizador valida o editor interativo antes de concluir.');
$check(str_contains($install, 'smoke-repertoire-livewire.php'), 'Instalador valida o editor interativo.');

if ($fail > 0) {
    echo "Resultado: FALHA ($fail teste(s))".PHP_EOL;
    exit(1);
}

echo "Resultado: OK".PHP_EOL;
