<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('repertoire_items', function (Blueprint $table): void {
            $table->foreignUuid('minister_person_id')
                ->nullable()
                ->after('song_version_id')
                ->references('id')
                ->on('people')
                ->nullOnDelete();
            $table->index(['minister_person_id', 'song_id'], 'repertoire_item_minister_song_idx');
        });

        Schema::table('repertoires', function (Blueprint $table): void {
            $table->foreignUuid('opening_minister_person_id')
                ->nullable()
                ->after('opening_song_version_id')
                ->references('id')
                ->on('people')
                ->nullOnDelete();
        });

        Schema::create('minister_song_keys', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('person_id')->references('id')->on('people')->cascadeOnDelete();
            $table->foreignUuid('song_id')->references('id')->on('songs')->cascadeOnDelete();
            $table->string('preferred_key', 20);
            $table->string('source', 30)->default('repertoire');
            $table->foreignUuid('last_service_id')->nullable()->references('id')->on('services')->nullOnDelete();
            $table->foreignUuid('last_repertoire_item_id')->nullable()->references('id')->on('repertoire_items')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestampTz('last_used_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'person_id', 'song_id'], 'minister_song_key_unique');
            $table->index(['person_id', 'song_id'], 'minister_song_key_lookup_idx');
            $table->index(['song_id', 'preferred_key'], 'minister_song_key_song_idx');
        });

        // Repertórios antigos com apenas um ministro publicado podem receber o
        // vínculo automaticamente sem risco de atribuir um louvor à pessoa errada.
        DB::table('repertoires')->select(['id', 'service_id'])->orderBy('created_at')->chunk(200, function ($repertoires): void {
            foreach ($repertoires as $repertoire) {
                $primary = DB::table('schedule_assignments')
                    ->where('service_id', $repertoire->service_id)
                    ->where('status', 'published')
                    ->whereNull('deleted_at')
                    ->value('minister_person_id');

                if (! $primary) continue;

                $additionalCount = DB::table('service_additional_ministers')
                    ->where('service_id', $repertoire->service_id)
                    ->where('status', 'published')
                    ->whereNull('deleted_at')
                    ->count();

                if ($additionalCount !== 0) continue;

                DB::table('repertoires')->where('id', $repertoire->id)->update(['opening_minister_person_id' => $primary]);
                DB::table('repertoire_items')->where('repertoire_id', $repertoire->id)->whereNull('minister_person_id')->update(['minister_person_id' => $primary]);
            }
        }, 'id');

        // O histórico efetivo já existente é a melhor fonte para o "último tom".
        // Percorremos do mais antigo ao mais novo; o registro mais recente vence.
        DB::table('song_executions as song_exec')
            ->join('service_executions as service_exec', 'service_exec.id', '=', 'song_exec.service_execution_id')
            ->whereNotNull('song_exec.song_id')
            ->whereNotNull('song_exec.performed_by_person_id')
            ->whereNotNull('song_exec.performed_key')
            ->whereNull('song_exec.deleted_at')
            ->select([
                'song_exec.organization_id',
                'song_exec.song_id',
                'song_exec.performed_by_person_id',
                'song_exec.performed_key',
                'song_exec.repertoire_item_id',
                'song_exec.recorded_by',
                'song_exec.created_at',
                'service_exec.service_id',
            ])
            ->orderBy('song_exec.created_at')
            ->orderBy('song_exec.id')
            ->chunk(250, function ($rows): void {
                foreach ($rows as $row) {
                    $key = trim((string) $row->performed_key);
                    if ($key === '') continue;

                    $where = [
                        'organization_id' => $row->organization_id,
                        'person_id' => $row->performed_by_person_id,
                        'song_id' => $row->song_id,
                    ];
                    $values = [
                        'preferred_key' => $key,
                        'source' => 'history',
                        'last_service_id' => $row->service_id,
                        'last_repertoire_item_id' => $row->repertoire_item_id,
                        'updated_by' => $row->recorded_by,
                        'last_used_at' => $row->created_at,
                        'updated_at' => now(),
                    ];

                    $existing = DB::table('minister_song_keys')->where($where)->value('id');
                    if ($existing) {
                        DB::table('minister_song_keys')->where('id', $existing)->update($values);
                    } else {
                        DB::table('minister_song_keys')->insert($where + $values + [
                            'id' => (string) Str::uuid(),
                            'created_at' => now(),
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('minister_song_keys');

        Schema::table('repertoires', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('opening_minister_person_id');
        });

        Schema::table('repertoire_items', function (Blueprint $table): void {
            $table->dropIndex('repertoire_item_minister_song_idx');
            $table->dropConstrainedForeignId('minister_person_id');
        });
    }
};
