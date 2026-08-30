<?php

$root = dirname(__DIR__);
$checks = [];

$check = function (bool $condition, string $ok, string $fail) use (&$checks): void {
    if ($condition) {
        echo "[OK] {$ok}\n";
        $checks[] = true;
        return;
    }
    fwrite(STDERR, "[ERRO] {$fail}\n");
    $checks[] = false;
};

$rehearsalView = file_get_contents($root.'/resources/views/rehearsals/index.blade.php') ?: '';
$editor = file_get_contents($root.'/app/Livewire/RepertoireEditor.php') ?: '';
$editorView = file_get_contents($root.'/resources/views/livewire/repertoire-editor.blade.php') ?: '';

$check(
    str_contains($rehearsalView, 'Ouvir no YouTube') && str_contains($rehearsalView, 'original_key'),
    'Ensaios exibem tom e referência clicável do YouTube.',
    'Ensaios não exibem tom/referência do louvor.'
);
$check(
    str_contains($rehearsalView, 'rehearsal-song-preview') && str_contains($rehearsalView, 'rehearsal-song-key'),
    'Cadastro de ensaio mostra prévia de tom e referência ao selecionar um louvor.',
    'Prévia de tom/referência no cadastro de ensaio ausente.'
);
$check(
    str_contains($rehearsalView, 'data-loading-label') && str_contains($rehearsalView, 'Adicionando...'),
    'Botão de ensaio informa processamento e evita clique duplicado.',
    'Botão de ensaio não informa estado de processamento.'
);
$check(
    str_contains($editor, "public string \$actionFeedback = '';") && str_contains($editor, 'Adicionado! O louvor entrou no repertório'),
    'Editor registra confirmação imediata após adicionar louvor.',
    'Confirmação imediata do repertório ausente no componente.'
);
$check(
    str_contains($editorView, 'aria-live="polite"') && str_contains($editorView, 'Operação concluída'),
    'Editor exibe confirmação visível independentemente da posição da página.',
    'Aviso visual de sucesso do repertório ausente.'
);
$check(
    str_contains($editorView, 'Aguarde a confirmação antes de continuar.'),
    'Editor orienta o usuário durante conexão lenta.',
    'Feedback de espera durante inclusão de louvor ausente.'
);

if (in_array(false, $checks, true)) {
    fwrite(STDERR, 'Resultado: FALHA ('.count(array_filter($checks, fn ($v) => !$v))." teste(s))\n");
    exit(1);
}

echo "Resultado: OK\n";
