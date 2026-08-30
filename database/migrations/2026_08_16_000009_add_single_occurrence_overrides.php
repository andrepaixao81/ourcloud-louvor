<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('recurrence_exceptions', function (Blueprint $table) {
            $table->foreignUuid('service_type_id')->nullable()->constrained('service_types')->nullOnDelete();
            $table->foreignUuid('congregation_id')->nullable()->constrained('congregations')->nullOnDelete();
            $table->foreignUuid('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('name', 180)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 30)->nullable();
            $table->text('notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('recurrence_exceptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_id');
            $table->dropConstrainedForeignId('congregation_id');
            $table->dropConstrainedForeignId('service_type_id');
            $table->dropColumn(['name', 'description', 'status', 'notes']);
        });
    }
};
