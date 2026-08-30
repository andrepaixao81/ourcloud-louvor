<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\SongImportService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImportExampleSongs extends Command
{
    protected $signature = 'sgml:import-example-songs {--admin-email= : E-mail do usuário administrador que define a organização}';
    protected $description = 'Importa de forma idempotente o catálogo fictício de exemplo do OurCloud Louvor.';

    public function handle(SongImportService $importer): int
    {
        $email = trim((string) $this->option('admin-email'));
        if ($email === '') {
            $this->error('Informe --admin-email para identificar a organização.');
            return self::FAILURE;
        }

        $admin = User::query()->withoutGlobalScopes()->whereRaw('lower(email) = ?', [mb_strtolower($email)])->first();
        if (! $admin || ! $admin->organization_id) {
            $this->error('Usuário/organização não encontrados para o e-mail informado.');
            return self::FAILURE;
        }

        Auth::login($admin);
        app()->instance('request', Request::create('/artisan/sgml-import', 'POST'));
        $stats = $importer->importBuiltIn($admin);

        $this->info(sprintf(
            'Importação concluída: %d itens; %d criados, %d enriquecidos, %d já adequados, %d sinalizados para revisão.',
            $stats['total'], $stats['created'], $stats['updated'], $stats['unchanged'], $stats['review']
        ));
        $this->warn('Nenhuma song_execution foi criada; a lista não possui datas de culto e o histórico não será artificialmente incrementado.');

        return self::SUCCESS;
    }
}
