<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('minister_availabilities', function (Blueprint $table): void {
            if (! Schema::hasColumn('minister_availabilities', 'rule_scope')) {
                $table->string('rule_scope', 30)->default('weekday');
            }
        });

        DB::table('minister_availabilities')
            ->whereNull('rule_scope')
            ->orWhere('rule_scope', '')
            ->update(['rule_scope' => 'weekday']);
    }

    public function down(): void
    {
        Schema::table('minister_availabilities', function (Blueprint $table): void {
            if (Schema::hasColumn('minister_availabilities', 'rule_scope')) {
                $table->dropColumn('rule_scope');
            }
        });
    }
};
