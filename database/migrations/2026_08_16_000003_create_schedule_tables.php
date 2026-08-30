<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('schedule_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('service_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('minister_person_id')->references('id')->on('people')->restrictOnDelete();
            $table->string('status', 30)->default('draft');
            $table->decimal('score', 6, 2)->nullable();
            $table->json('explanation')->nullable();
            $table->foreignUuid('assigned_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->foreignUuid('published_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['service_id']);
            $table->index(['organization_id', 'status']);
            $table->index(['minister_person_id', 'status']);
        });

        Schema::create('schedule_confirmations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('schedule_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('person_id')->references('id')->on('people')->cascadeOnDelete();
            $table->string('status', 30)->default('pending');
            $table->text('notes')->nullable();
            $table->timestampTz('responded_at')->nullable();
            $table->timestamps();
            $table->unique(['schedule_assignment_id', 'person_id']);
        });

        Schema::create('swap_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('schedule_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('requester_person_id')->references('id')->on('people')->restrictOnDelete();
            $table->foreignUuid('target_person_id')->nullable()->references('id')->on('people')->restrictOnDelete();
            $table->foreignUuid('reciprocal_assignment_id')->nullable()->references('id')->on('schedule_assignments')->nullOnDelete();
            $table->string('type', 30)->default('replacement');
            $table->string('status', 30)->default('requested');
            $table->text('reason')->nullable();
            $table->foreignUuid('approved_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
        });

        Schema::create('swap_request_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('swap_request_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 40);
            $table->text('notes')->nullable();
            $table->timestampTz('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('swap_request_actions');
        Schema::dropIfExists('swap_requests');
        Schema::dropIfExists('schedule_confirmations');
        Schema::dropIfExists('schedule_assignments');
    }
};
