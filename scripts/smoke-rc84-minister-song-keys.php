<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = 0;
$checks = 0;

$check = function (bool $condition, string $label) use (&$failures, &$checks): void {
    $checks++;
    if ($condition) {
        echo "[OK] {$label}\n";
        return;
    }
    $failures++;
    fwrite(STDERR, "[FALHA] {$label}\n");
};

$read = static function (string $relative) use ($root): string {
    $path = $root.'/'.$relative;
    return is_file($path) ? (string) file_get_contents($path) : '';
};

$migration = $read('database/migrations/2026_08_29_000029_rc84_minister_song_keys.php');
$keyService = $read('app/Services/MinisterSongKeyService.php');
$repertoireService = $read('app/Services/RepertoireService.php');
$editor = $read('app/Livewire/RepertoireEditor.php');
$editorView = $read('resources/views/livewire/repertoire-editor.blade.php');
$rehearsalController = $read('app/Http/Controllers/RehearsalController.php');
$rehearsalView = $read('resources/views/rehearsals/index.blade.php');
$routes = $read('routes/web.php');
$history = $read('app/Services/ServiceHistorySyncService.php');
$execution = $read('app/Services/ExecutionService.php');
$executionController = $read('app/Http/Controllers/ExecutionController.php');
$executionView = $read('resources/views/executions/show.blade.php');
$calendar = $read('app/Services/CalendarEventContentService.php');
$pdf = $read('app/Services/SchedulePdfService.php');
$songShow = $read('resources/views/songs/show.blade.php');
$install = $read('deploy/install.sh');

$check(in_array(trim($read('VERSION')), ['1.0.0-rc84', '1.0.0-rc85', '1.0.0-rc86', '1.0.0-rc87', '1.0.0-rc88'], true), 'Versão da distribuição é RC84.');
$check(str_contains($migration, "Schema::create('minister_song_keys'"), 'Migration cria memória de tom por ministro e louvor.');
$check(str_contains($migration, "minister_person_id") && str_contains($migration, "opening_minister_person_id"), 'Repertório vincula cada louvor e abertura ao ministro responsável.');
$check(str_contains($keyService, 'preferredKey') && str_contains($keyService, 'remember('), 'Serviço consulta e memoriza o tom personalizado.');
$check(str_contains($repertoireService, 'validateMinister') && str_contains($repertoireService, 'rememberKey'), 'Repertório valida ministro escalado e persiste seu tom.');
$check(str_contains($editor, 'songMinisterId') && str_contains($editor, 'applySongSuggestion') && str_contains($editor, 'MinisterSongKeyService'), 'Editor sugere o último tom do ministro ao selecionar o louvor.');
$check(str_contains($editorView, 'Quem ministrará') && str_contains($editorView, 'Tom deste ministro'), 'UI do repertório expõe ministro e tom individual.');
$check(str_contains($routes, 'rehearsals.repertoire-key.update') && str_contains($routes, 'rehearsals.opening-key.update'), 'Rotas permitem registrar tons durante o ensaio.');
$check(str_contains($rehearsalController, "'rehearsal'") && str_contains($rehearsalController, 'authorizeKeyChange'), 'Ensaio grava memória com autorização contextual.');
$check(str_contains($rehearsalView, 'Tons do ensaio dos próximos cultos') && str_contains($rehearsalView, 'Salvar tom'), 'Tela de ensaio mostra e permite registrar os tons dos próximos cultos.');
$check(str_contains($history, 'opening_minister_person_id') && str_contains($history, 'minister_person_id') && str_contains($history, 'rememberExecution'), 'Histórico efetivo preserva quem ministrou e atualiza o último tom.');
$check(str_contains($execution, 'preferredKey') && str_contains($execution, 'rememberPerformedKey'), 'Modo Culto reutiliza e atualiza o tom personalizado.');
$check(str_contains($executionController, 'item->minister_person_id') && str_contains($executionView, 'Ministro:'), 'Modo Culto preserva o ministro definido no repertório ao registrar a execução.');
$check(str_contains($calendar, 'Ministro:') && str_contains($calendar, 'repertoire.items.minister'), 'Calendário sincronizado informa ministro e tom do louvor.');
$check(str_contains($pdf, 'openingMinister') && str_contains($pdf, 'item->minister'), 'PDF da escala inclui o ministro responsável por cada louvor.');
$check(str_contains($songShow, 'Tons lembrados por ministro'), 'Biblioteca do louvor exibe os tons lembrados por ministro.');
$check(str_contains($install, 'APP_VERSION=1.0.0-rc88') && str_contains($install, 'smoke-rc84-minister-song-keys.php'), 'Instalação limpa provisiona e valida a RC84.');

if ($failures > 0) {
    fwrite(STDERR, "\nRC84: {$failures} de {$checks} verificações falharam.\n");
    exit(1);
}

echo "\nRC84: {$checks}/{$checks} verificações aprovadas.\n";
