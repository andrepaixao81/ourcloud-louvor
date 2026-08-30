<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('slug', 160);
            $table->text('description')->nullable();
            $table->boolean('requires_authorization')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'slug']);
        });

        Schema::create('special_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at')->nullable();
            $table->string('timezone', 100);
            $table->string('status', 30)->default('scheduled');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'starts_at']);
        });

        Schema::create('recurring_service_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('service_type_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('congregation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 180);
            $table->string('frequency', 30); // weekly, monthly_nth, monthly_date
            $table->unsignedSmallInteger('interval')->default(1);
            $table->smallInteger('weekday')->nullable(); // 0=domingo ... 6=sábado
            $table->smallInteger('week_of_month')->nullable(); // 1..5 ou -1
            $table->smallInteger('day_of_month')->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->time('starts_time');
            $table->time('ends_time')->nullable();
            $table->string('timezone', 100)->default('America/Sao_Paulo');
            $table->boolean('active')->default(true);
            $table->foreignUuid('created_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'active']);
        });

        Schema::create('services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('service_type_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('congregation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('special_event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('recurring_rule_id')->nullable()->references('id')->on('recurring_service_rules')->nullOnDelete();
            $table->string('recurrence_occurrence_key', 180)->nullable();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at')->nullable();
            $table->string('timezone', 100)->default('America/Sao_Paulo');
            $table->string('status', 30)->default('draft');
            $table->string('origin', 30)->default('manual');
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'starts_at']);
            $table->index(['organization_id', 'status']);
            $table->unique(['recurring_rule_id', 'recurrence_occurrence_key']);
        });

        Schema::create('recurrence_exceptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('recurring_rule_id')->references('id')->on('recurring_service_rules')->cascadeOnDelete();
            $table->string('occurrence_key', 180);
            $table->string('exception_type', 30); // modified/cancelled/skipped
            $table->timestampTz('original_starts_at');
            $table->timestampTz('new_starts_at')->nullable();
            $table->timestampTz('new_ends_at')->nullable();
            $table->text('reason')->nullable();
            $table->foreignUuid('created_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['recurring_rule_id', 'occurrence_key']);
        });

        Schema::create('minister_availabilities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('person_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('weekday');
            $table->time('starts_time')->nullable();
            $table->time('ends_time')->nullable();
            $table->string('period', 20)->nullable(); // morning/afternoon/evening/any
            $table->string('parity_rule', 20)->default('any'); // any/even/odd
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['person_id', 'weekday']);
        });

        Schema::create('minister_unavailabilities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('person_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('reason_type', 30)->default('personal');
            $table->text('private_notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['person_id', 'starts_at', 'ends_at']);
        });

        Schema::create('minister_service_permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('person_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('service_type_id')->constrained()->cascadeOnDelete();
            $table->boolean('allowed')->default(true);
            $table->timestamps();
            $table->unique(['person_id', 'service_type_id']);
        });

        Schema::create('minister_congregation_permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('person_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('congregation_id')->constrained()->cascadeOnDelete();
            $table->boolean('allowed')->default(true);
            $table->timestamps();
            $table->unique(['person_id', 'congregation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('minister_congregation_permissions');
        Schema::dropIfExists('minister_service_permissions');
        Schema::dropIfExists('minister_unavailabilities');
        Schema::dropIfExists('minister_availabilities');
        Schema::dropIfExists('recurrence_exceptions');
        Schema::dropIfExists('services');
        Schema::dropIfExists('recurring_service_rules');
        Schema::dropIfExists('special_events');
        Schema::dropIfExists('service_types');
    }
};
