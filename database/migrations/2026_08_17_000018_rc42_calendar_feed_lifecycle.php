<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('calendar_feed_states', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('scope', 20); // personal / team
            $table->uuid('service_id');
            $table->uuid('calendar_entry_id')->nullable();
            $table->timestampTz('event_starts_at')->nullable();
            $table->string('status', 20)->default('active'); // active / cancelled
            $table->char('content_hash', 64)->nullable();
            $table->unsignedBigInteger('sequence')->default(0);
            $table->timestampTz('last_published_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'scope', 'service_id'], 'calendar_feed_states_user_scope_service_unique');
            $table->index(['organization_id', 'scope', 'status'], 'calendar_feed_states_org_scope_status_idx');
            $table->index(['user_id', 'scope', 'event_starts_at'], 'calendar_feed_states_user_scope_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_feed_states');
    }
};
