<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('whatsapp_settings')) {
            Schema::table('whatsapp_settings', function (Blueprint $table): void {
                if (! Schema::hasColumn('whatsapp_settings', 'provider')) {
                    $table->string('provider', 30)->default('meta');
                }
                if (! Schema::hasColumn('whatsapp_settings', 'evolution_base_url')) {
                    $table->string('evolution_base_url', 500)->nullable();
                }
                if (! Schema::hasColumn('whatsapp_settings', 'evolution_instance')) {
                    $table->string('evolution_instance', 160)->nullable();
                }
                if (! Schema::hasColumn('whatsapp_settings', 'evolution_api_key')) {
                    $table->text('evolution_api_key')->nullable();
                }
                if (! Schema::hasColumn('whatsapp_settings', 'evolution_delay_ms')) {
                    $table->unsignedInteger('evolution_delay_ms')->default(500);
                }
                if (! Schema::hasColumn('whatsapp_settings', 'evolution_link_preview')) {
                    $table->boolean('evolution_link_preview')->default(true);
                }
            });

            DB::table('whatsapp_settings')->whereNull('provider')->update(['provider' => 'meta']);
        }

        if (Schema::hasTable('whatsapp_delivery_logs')) {
            Schema::table('whatsapp_delivery_logs', function (Blueprint $table): void {
                if (! Schema::hasColumn('whatsapp_delivery_logs', 'provider')) {
                    $table->string('provider', 30)->nullable()->index();
                }
            });
        }

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')
                ->where('name', 'administration.whatsapp')
                ->update([
                    'description' => 'Configurar avisos automáticos pelo WhatsApp (Evolution API ou Meta Cloud API)',
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('whatsapp_delivery_logs') && Schema::hasColumn('whatsapp_delivery_logs', 'provider')) {
            Schema::table('whatsapp_delivery_logs', function (Blueprint $table): void {
                $table->dropColumn('provider');
            });
        }

        if (Schema::hasTable('whatsapp_settings')) {
            Schema::table('whatsapp_settings', function (Blueprint $table): void {
                foreach ([
                    'provider', 'evolution_base_url', 'evolution_instance', 'evolution_api_key',
                    'evolution_delay_ms', 'evolution_link_preview',
                ] as $column) {
                    if (Schema::hasColumn('whatsapp_settings', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
