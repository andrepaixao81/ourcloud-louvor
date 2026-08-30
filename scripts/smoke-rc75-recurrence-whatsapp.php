<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = 0;
$ok = static function (bool $condition, string $message) use (&$failures): void {
    if ($condition) {
        echo "[OK] {$message}\n";
    } else {
        echo "[ERRO] {$message}\n";
        $failures++;
    }
};

$recurring = (string) file_get_contents($root.'/app/Services/RecurringServiceGeneratorService.php');
$scheduleGenerator = (string) file_get_contents($root.'/app/Services/ScheduleGeneratorService.php');
$scheduleController = (string) file_get_contents($root.'/app/Http/Controllers/ScheduleController.php');
$scheduleView = (string) file_get_contents($root.'/resources/views/schedules/index.blade.php');
$repertoire = (string) file_get_contents($root.'/app/Livewire/RepertoireEditor.php');
$team = (string) file_get_contents($root.'/app/Services/TeamNotificationService.php');

$ok(str_contains($recurring, 'hasSpecialOverrideForDate'), 'Regra especial mensal tem precedência automática sobre semanal no mesmo slot.');
$ok(str_contains($recurring, "whereIn('frequency', ['monthly_nth', 'monthly_date'])"), 'Precedência cobre regras mensais especiais.');
$ok(str_contains($recurring, 'reconcileOrganizationMaterialized'), 'Ocorrências materializadas antigas são reconciliadas.');
$ok(str_contains($recurring, "orWhere('origin', 'recurrence')"), 'Ocorrências recorrentes órfãs também são reconciliadas.');
$ok(str_contains($scheduleGenerator, '$this->recurring->synchronizeMonth($month)'), 'Gerar/revalidar escala reconcilia regras antes do rodízio.');
$ok(str_contains($scheduleController, '$recurring->synchronizeMonth($month)'), 'Publicação revalida recorrência antes de publicar.');
$ok(str_contains($scheduleController, "whereNotIn('status', ['cancelled', 'completed', 'in_progress'])"), 'Publicação não republica cultos cancelados.');

$ok(str_contains($repertoire, "'Ministro: '.\$primaryMinister"), 'WhatsApp do repertório inclui nome do ministro.');
$ok(str_contains($repertoire, "'Dia: '.\$weekday"), 'WhatsApp do repertório inclui dia da semana.');
$ok(str_contains($repertoire, "'Data: '.\$startsAt->format('d/m/Y')"), 'WhatsApp do repertório inclui data.');
$ok(str_contains($repertoire, "'Horário: '.\$startsAt->format('H:i')"), 'WhatsApp do repertório inclui horário.');
$ok(str_contains($repertoire, "'Abertura: '.\$this->repertoire->openingSong->title"), 'WhatsApp do repertório inclui abertura em linha própria.');
$ok(str_contains($repertoire, "'Louvor '.(\$index + 1).': '.\$item->song->title"), 'WhatsApp do repertório lista louvores numerados linha a linha.');
$ok(substr_count($repertoire, "'Tom: '.") >= 2, 'WhatsApp do repertório inclui tom da abertura e dos louvores.');

$ok(str_contains($scheduleView, 'name="notify_whatsapp"'), 'Tela de publicação oferece checkbox de WhatsApp.');
$ok(str_contains($scheduleController, '$request->boolean(\'notify_whatsapp\')'), 'Publicação respeita a escolha do checkbox.');
$ok(str_contains($team, 'bool $sendWhatsapp = true'), 'Serviço de avisos permite desligar apenas o canal WhatsApp.');
$ok(str_contains($team, '! $sendWhatsapp || ! $settings?->isConfigured()'), 'Aviso interno permanece mesmo quando WhatsApp é desmarcado.');

$version = trim((string) file_get_contents($root.'/VERSION'));
$rc = preg_match('/^1\.0\.0-rc(\d+)$/', $version, $m) ? (int) $m[1] : 0;
$ok($rc >= 75, 'Versão do pacote é RC75 ou posterior.');

echo $failures === 0 ? "Resultado: OK\n" : "Resultado: FALHA ({$failures})\n";
exit($failures === 0 ? 0 : 1);
