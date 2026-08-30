<?php

namespace App\Console\Commands;

use App\Models\ActionItem;
use Illuminate\Console\Command;

class SendReminders extends Command
{
    protected $signature = 'sgml:reminders';
    protected $description = 'Processa lembretes internos pendentes';
    public function handle(): int
    {
        $count=ActionItem::query()->withoutGlobalScopes()->where('status','pending')->whereNotNull('due_at')->where('due_at','<=',now())->count();
        $this->info("Lembretes pendentes vencidos: {$count}");
        return self::SUCCESS;
    }
}
