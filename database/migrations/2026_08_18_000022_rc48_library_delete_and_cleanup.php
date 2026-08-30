<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Itens arquivados nas versões anteriores deixam de aparecer na biblioteca.
        // Mantemos a linha para referências históricas por meio de SoftDeletes.
        DB::table('songs')
            ->where('status', 'inactive')
            ->whereNull('deleted_at')
            ->update([
                'status' => 'deleted',
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('songs')
            ->where('status', 'deleted')
            ->whereNotNull('deleted_at')
            ->update([
                'status' => 'inactive',
                'deleted_at' => null,
                'updated_at' => now(),
            ]);
    }
};
