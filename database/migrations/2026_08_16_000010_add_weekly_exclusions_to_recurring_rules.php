<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('recurring_service_rules', function (Blueprint $table): void {
            $table->json('excluded_weeks_of_month')->nullable()->after('week_of_month');
        });
    }

    public function down(): void
    {
        Schema::table('recurring_service_rules', function (Blueprint $table): void {
            $table->dropColumn('excluded_weeks_of_month');
        });
    }
};
