<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        DB::table('users')
            ->whereNull('deleted_at')
            ->orderBy('created_at')
            ->get()
            ->each(function ($user): void {
                $alreadyLinked = DB::table('people')
                    ->where('organization_id', $user->organization_id)
                    ->where('user_id', $user->id)
                    ->whereNull('deleted_at')
                    ->exists();
                if ($alreadyLinked) return;

                $person = DB::table('people')
                    ->where('organization_id', $user->organization_id)
                    ->whereNull('user_id')
                    ->whereNull('deleted_at')
                    ->whereNotNull('email')
                    ->whereRaw('LOWER(email) = LOWER(?)', [$user->email])
                    ->orderBy('created_at')
                    ->first();

                if ($person) {
                    DB::table('people')->where('id', $person->id)->update([
                        'user_id' => $user->id,
                        'updated_at' => now(),
                    ]);
                    return;
                }

                $parts = preg_split('/\s+/', trim((string) $user->name)) ?: [];
                $firstName = array_shift($parts) ?: 'Usuário';
                $lastName = $parts ? implode(' ', $parts) : null;

                DB::table('people')->insert([
                    'id' => (string) Str::uuid(),
                    'organization_id' => $user->organization_id,
                    'user_id' => $user->id,
                    'congregation_id' => null,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'display_name' => $user->name,
                    'email' => $user->email,
                    'phone' => null,
                    'birth_date' => null,
                    'joined_at' => null,
                    'status' => 'active',
                    'admin_notes' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'deleted_at' => null,
                ]);
            });

        Schema::table('people', function (Blueprint $table): void {
            $table->index(['organization_id', 'user_id'], 'idx_people_org_user_rc82');
        });
        Schema::table('swap_requests', function (Blueprint $table): void {
            $table->index(['target_person_id', 'status'], 'idx_swap_target_status_rc82');
        });
        Schema::table('services', function (Blueprint $table): void {
            $table->index(['organization_id', 'status', 'starts_at'], 'idx_services_org_status_start_rc82');
        });
        Schema::table('calendar_entries', function (Blueprint $table): void {
            $table->index(['organization_id', 'status', 'starts_at'], 'idx_calendar_org_status_start_rc82');
        });
    }

    public function down(): void
    {
        Schema::table('calendar_entries', fn (Blueprint $table) => $table->dropIndex('idx_calendar_org_status_start_rc82'));
        Schema::table('services', fn (Blueprint $table) => $table->dropIndex('idx_services_org_status_start_rc82'));
        Schema::table('swap_requests', fn (Blueprint $table) => $table->dropIndex('idx_swap_target_status_rc82'));
        Schema::table('people', fn (Blueprint $table) => $table->dropIndex('idx_people_org_user_rc82'));
    }
};
