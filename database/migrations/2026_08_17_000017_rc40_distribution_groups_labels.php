<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')
            ->where('name', 'song_history.view')
            ->update(['description' => 'Visualizar histórico de louvores ministrados, ministros e tons']);
    }

    public function down(): void
    {
        DB::table('permissions')
            ->where('name', 'song_history.view')
            ->update(['description' => 'Visualizar histórico de músicas ministradas, ministros e tons']);
    }
};
