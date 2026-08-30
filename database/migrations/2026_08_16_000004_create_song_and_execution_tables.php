<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('song_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'name']);
        });

        Schema::create('songs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('category_id')->nullable()->references('id')->on('song_categories')->nullOnDelete();
            $table->string('title', 180);
            $table->string('normalized_title', 180);
            $table->string('alternative_title', 180)->nullable();
            $table->string('normalized_alternative_title', 180)->nullable();
            $table->string('artist_reference', 180)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('active');
            $table->foreignUuid('created_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->foreignUuid('approved_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'normalized_title']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('song_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('song_id')->constrained()->cascadeOnDelete();
            $table->string('name', 180);
            $table->string('artist', 180)->nullable();
            $table->string('youtube_url', 500)->nullable();
            $table->string('original_key', 20)->nullable();
            $table->unsignedSmallInteger('bpm')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['song_id', 'active']);
        });

        Schema::create('song_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('suggested_title', 180);
            $table->string('normalized_title', 180);
            $table->string('artist_reference', 180)->nullable();
            $table->string('youtube_url', 500)->nullable();
            $table->string('suggested_key', 20)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('pending');
            $table->foreignUuid('reviewed_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignUuid('approved_song_id')->nullable()->references('id')->on('songs')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
        });

        Schema::create('repertoires', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('service_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('draft');
            $table->unsignedInteger('revision_number')->default(1);
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->foreignUuid('published_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('locked_at')->nullable();
            $table->foreignUuid('locked_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique('service_id');
        });

        Schema::create('repertoire_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('repertoire_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('song_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('song_version_id')->nullable()->references('id')->on('song_versions')->nullOnDelete();
            $table->unsignedInteger('position');
            $table->string('planned_key', 20)->nullable();
            $table->string('specific_youtube_url', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['repertoire_id', 'position']);
        });

        Schema::create('service_executions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('service_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('not_started');
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->foreignUuid('opened_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->foreignUuid('closed_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->text('correction_reason')->nullable();
            $table->timestamps();
            $table->unique('service_id');
            $table->index(['organization_id', 'status']);
        });

        Schema::create('song_executions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('service_execution_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('song_id')->nullable()->references('id')->on('songs')->nullOnDelete();
            $table->foreignUuid('song_request_id')->nullable()->references('id')->on('song_requests')->nullOnDelete();
            $table->foreignUuid('song_version_id')->nullable()->references('id')->on('song_versions')->nullOnDelete();
            $table->foreignUuid('repertoire_item_id')->nullable()->references('id')->on('repertoire_items')->nullOnDelete();
            $table->string('song_title_snapshot', 180)->nullable();
            $table->unsignedInteger('position');
            $table->string('planned_key', 20)->nullable();
            $table->string('performed_key', 20)->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->string('execution_source', 40)->default('repertoire');
            $table->text('notes')->nullable();
            $table->foreignUuid('recorded_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['song_id', 'created_at']);
            $table->index(['service_execution_id', 'position']);
        });

        Schema::create('service_execution_ministers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('service_execution_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('person_id')->references('id')->on('people')->restrictOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->foreignUuid('recorded_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['service_execution_id', 'person_id']);
        });

        Schema::create('service_participants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('service_execution_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('person_id')->references('id')->on('people')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->foreignUuid('recorded_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['service_execution_id', 'person_id']);
        });

        Schema::create('service_participant_roles', function (Blueprint $table) {
            $table->foreignUuid('service_participant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('ministry_role_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->primary(['service_participant_id', 'ministry_role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_participant_roles');
        Schema::dropIfExists('service_participants');
        Schema::dropIfExists('service_execution_ministers');
        Schema::dropIfExists('song_executions');
        Schema::dropIfExists('service_executions');
        Schema::dropIfExists('repertoire_items');
        Schema::dropIfExists('repertoires');
        Schema::dropIfExists('song_requests');
        Schema::dropIfExists('song_versions');
        Schema::dropIfExists('songs');
        Schema::dropIfExists('song_categories');
    }
};
