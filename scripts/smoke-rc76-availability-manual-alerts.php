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

$availabilityView = $read('resources/views/calendar/availability.blade.php');
$availabilityController = $read('app/Http/Controllers/AvailabilityController.php');
$availabilityService = $read('app/Services/AvailabilityService.php');
$scheduleController = $read('app/Http/Controllers/ScheduleController.php');
$scheduleValidation = $read('app/Services/ScheduleValidationService.php');
$scheduleView = $read('resources/views/schedules/index.blade.php');
$rehearsalController = $read('app/Http/Controllers/RehearsalController.php');
$specialEventController = $read('app/Http/Controllers/SpecialEventController.php');
$migration = $read('database/migrations/2026_08_20_000025_rc76_availability_scope.php');

$assert(str_contains($migration, "rule_scope"), 'Migration RC76 cria escopo explícito para regras de disponibilidade.');
$assert(str_contains($availabilityView, 'Regra avançada por data par/ímpar'), 'Disponibilidade par/ímpar está visível como modalidade própria.');
$assert(str_contains($availabilityView, 'name="rule_scope" value="month_parity"'), 'Formulário par/ímpar usa escopo mensal independente do dia da semana.');
$assert(str_contains($availabilityView, 'Por dia da semana'), 'Modalidade por dia da semana continua disponível separadamente.');
$assert(str_contains($availabilityController, "'rule_scope' => ['required', 'in:month_parity']"), 'Controller valida regra mensal par/ímpar.');
$assert(str_contains($availabilityService, "if (\$scope === 'month_parity')"), 'Motor de disponibilidade aplica paridade mensal sem exigir weekday.');

$assert(str_contains($availabilityService, 'evaluateForManualOverride'), 'Motor possui avaliação de exceção manual para liderança.');
$assert(str_contains($availabilityService, 'same_day_emergency_override'), 'Mesmo dia é tratado como advertência em escolha deliberada.');
$assert(str_contains($availabilityService, 'Conflito de horário'), 'Conflito real de horário continua sendo bloqueio.');
$assert(str_contains($scheduleController, 'evaluateForManualOverride'), 'Alteração direta da escala usa a avaliação com advertência de mesmo dia.');
$assert(str_contains($scheduleView, '⚠ já escalado neste dia'), 'Dropdown da escala torna a advertência de mesmo dia visível.');
$assert(str_contains($scheduleView, 'dois cultos do mesmo dia'), 'Tela explica que a exceção manual de mesmo dia é permitida.');
$assert(str_contains($scheduleValidation, 'Exceção manual permitida pela liderança'), 'Validação mensal registra mesmo dia como advertência, não pendência impeditiva.');

$assert(str_contains($rehearsalController, 'TeamNotificationService'), 'Cadastro de louvor em ensaio usa serviço de alertas da equipe.');
$assert(str_contains($rehearsalController, 'Novo louvor em ensaio:'), 'Novo louvor em ensaio gera alerta explícito.');
$assert(str_contains($specialEventController, 'Confirme no SGML se poderá participar.'), 'Evento especial envia alerta com chamada para confirmação.');
$assert(str_contains($specialEventController, "'Dia: '"), 'Alerta de evento especial informa o dia da semana.');

foreach ($checks as [$ok, $label]) {
    echo ($ok ? '[OK] ' : '[ERRO] ').$label."\n";
}

if ($failures !== []) {
    echo 'Resultado: FALHA ('.count($failures)." verificação(ões))\n";
    exit(1);
}

echo "Resultado: OK\n";
