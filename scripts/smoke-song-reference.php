<?php

$root = dirname(__DIR__);
$controller = file_get_contents($root.'/app/Http/Controllers/SongController.php');
$form = file_get_contents($root.'/resources/views/songs/form.blade.php');
$rehearsals = file_get_contents($root.'/resources/views/rehearsals/index.blade.php');

$checks = [
    'Cadastro cria versão mesmo quando há somente link/tom/BPM' => str_contains($controller, '$hasReferenceData = $request->filled(\'version_name\')')
        && str_contains($controller, '|| $request->filled(\'youtube_url\')')
        && str_contains($controller, '\'name\' => $request->filled(\'version_name\') ? trim((string) $request->version_name) : \'Versão padrão\''),
    'Edição carrega a referência padrão/existente' => str_contains($controller, '\'referenceVersion\' => $referenceVersion'),
    'Edição persiste link YouTube, tom e BPM na versão de referência' => str_contains($controller, '\'youtube_url\' => $request->filled(\'youtube_url\') ? trim((string) $request->youtube_url) : null')
        && str_contains($controller, '\'original_key\' => $request->filled(\'original_key\') ? trim((string) $request->original_key) : null')
        && str_contains($controller, '\'bpm\' => $request->filled(\'bpm\') ? (int) $request->bpm : null'),
    'Formulário de edição exibe Link de referência no YouTube' => str_contains($form, 'Link de referência no YouTube')
        && str_contains($form, 'name="youtube_url"')
        && str_contains($form, '$referenceVersion?->youtube_url'),
    'Formulário de edição exibe tom e BPM da referência' => str_contains($form, 'name="original_key"')
        && str_contains($form, 'name="bpm"'),
    'Ensaios prioriza versão padrão ativa e referência útil' => str_contains($rehearsals, '$version->is_default && $version->active')
        && str_contains($rehearsals, '$version->active && ($version->youtube_url || $version->original_key)'),
    'Ensaios continua exibindo link clicável do YouTube' => str_contains($rehearsals, '▶ Ouvir no YouTube')
        && str_contains($rehearsals, 'href="{{ $version->youtube_url }}"'),
];

$failed = 0;
foreach ($checks as $label => $ok) {
    if ($ok) {
        echo "[OK] {$label}.\n";
    } else {
        echo "[ERRO] {$label}.\n";
        $failed++;
    }
}

echo $failed === 0 ? "Resultado: OK\n" : "Resultado: FALHA ({$failed} teste(s))\n";
exit($failed === 0 ? 0 : 1);
