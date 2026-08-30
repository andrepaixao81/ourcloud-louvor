<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('sgml:about', function () {
    $this->info('Sistema de Gestão do Ministério de Louvor - v'.config('app.version'));
});

Schedule::command('queue:prune-failed --hours=168')->dailyAt('02:10');
Schedule::command('model:prune')->dailyAt('02:20');
Schedule::command('sgml:reminders')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('sgml:expand-recurring --days=90')->dailyAt('01:30')->withoutOverlapping();

// RC46: repertório da escala vira histórico automaticamente após o término do culto.
Schedule::command('sgml:finalize-planned-services --limit=250')->everyFiveMinutes()->withoutOverlapping();

// RC81: felicitações automáticas; o comando respeita horário e fuso de cada organização.
Schedule::command('sgml:birthday-greetings')->everyFifteenMinutes()->withoutOverlapping();
