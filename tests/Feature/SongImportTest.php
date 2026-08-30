<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SongImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_builtin_catalog_can_be_imported_from_the_web_without_creating_execution_history(): void
    {
        $org = $this->makeOrganization();
        $user = $this->makeUser($org, ['songs.create', 'songs.view']);

        $this->actingAs($user)->post(route('songs.import.catalog'))->assertRedirect(route('songs.index'));

        $this->assertDatabaseCount('songs', 95);
        $this->assertDatabaseCount('song_executions', 0);
    }

    public function test_csv_import_accepts_semicolon_delimited_files_and_is_idempotent(): void
    {
        $org = $this->makeOrganization();
        $user = $this->makeUser($org, ['songs.create', 'songs.view']);
        $csv = "titulo;artista;tom_original;categoria\nTeste de Louvor;Artista Teste;G;Adoração\n";

        $file = UploadedFile::fake()->createWithContent('louvores.csv', $csv);
        $this->actingAs($user)->post(route('songs.import.csv'), ['file' => $file])->assertRedirect(route('songs.index'));
        $this->assertDatabaseCount('songs', 1);

        $file2 = UploadedFile::fake()->createWithContent('louvores.csv', $csv);
        $this->actingAs($user)->post(route('songs.import.csv'), ['file' => $file2])->assertRedirect(route('songs.index'));
        $this->assertDatabaseCount('songs', 1);
    }
}
