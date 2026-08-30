<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DatabaseBackupService
{
    public function diagnostics(): array
    {
        $path = $this->backupPath();
        $pgDump = (string) config('backup.pg_dump', '/usr/bin/pg_dump');
        $pgRestore = (string) config('backup.pg_restore', '/usr/bin/pg_restore');
        $dispatcher = (string) config('backup.restore_dispatcher', '/usr/local/sbin/sgml-web-restore-dispatch');

        return [
            'path' => $path,
            'path_exists' => is_dir($path),
            'path_writable' => is_dir($path) && is_writable($path),
            'pg_dump' => $pgDump,
            'pg_dump_available' => is_executable($pgDump),
            'pg_restore' => $pgRestore,
            'pg_restore_available' => is_executable($pgRestore),
            'restore_dispatcher' => $dispatcher,
            'restore_dispatcher_available' => is_executable($dispatcher),
            'restore_request_path' => $this->restoreRequestPath(),
            'restore_request_writable' => is_dir($this->restoreRequestPath()) && is_writable($this->restoreRequestPath()),
            'restore_status_path' => $this->restoreStatusPath(),
            'restore_status_writable' => is_dir($this->restoreStatusPath()) && is_writable($this->restoreStatusPath()),
            'restore_parent_path' => dirname($this->restoreRequestPath()),
            'restore_parent_traversable' => is_dir(dirname($this->restoreRequestPath())) && is_executable(dirname($this->restoreRequestPath())),
            'database' => (string) config('database.connections.pgsql.database'),
            'server_version' => $this->serverVersion(),
            'app_key_fingerprint' => $this->appKeyFingerprint(),
            'upload_max_mb' => round(max(1, (int) config('backup.upload_max_kb', 524288)) / 1024),
        ];
    }

    public function list(): array
    {
        $path = $this->backupPath();
        if (! is_dir($path)) {
            return [];
        }

        $items = [];
        foreach (glob($path.'/sgml-db-*.json') ?: [] as $manifestPath) {
            $raw = @file_get_contents($manifestPath);
            if ($raw === false) {
                continue;
            }
            $manifest = json_decode($raw, true);
            if (! is_array($manifest) || empty($manifest['filename'])) {
                continue;
            }
            $dumpPath = $path.'/'.basename((string) $manifest['filename']);
            if (! is_file($dumpPath)) {
                continue;
            }
            $manifest['size_bytes'] = filesize($dumpPath) ?: 0;
            $manifest['downloadable'] = true;
            $manifest['app_key_matches'] = ! isset($manifest['app_key_fingerprint'])
                || $manifest['app_key_fingerprint'] === 'desconhecida'
                || hash_equals((string) $manifest['app_key_fingerprint'], $this->appKeyFingerprint());
            $items[] = $manifest;
        }

        usort($items, static fn (array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
        return $items;
    }

    public function create(string $userId, string $userName): array
    {
        $this->assertSupportedDatabase();
        $this->ensureDirectory();

        $diagnostics = $this->diagnostics();
        if (! $diagnostics['path_writable']) {
            throw new RuntimeException('Diretório de backups não está gravável pelo usuário do SGML.');
        }
        if (! $diagnostics['pg_dump_available']) {
            throw new RuntimeException('pg_dump não está disponível em '.(string) $diagnostics['pg_dump'].'.');
        }
        if (! $diagnostics['pg_restore_available']) {
            throw new RuntimeException('pg_restore não está disponível em '.(string) $diagnostics['pg_restore'].'.');
        }

        $base = $this->newBaseName();
        $filename = $base.'.dump';
        $dumpPath = $this->backupPath().'/'.$filename;
        $manifestPath = $this->backupPath().'/'.$base.'.json';
        $connection = config('database.connections.pgsql');
        $command = [
            (string) config('backup.pg_dump', '/usr/bin/pg_dump'),
            '--format=custom',
            '--no-owner',
            '--no-privileges',
            '--host='.(string) ($connection['host'] ?? '127.0.0.1'),
            '--port='.(string) ($connection['port'] ?? '5432'),
            '--username='.(string) ($connection['username'] ?? ''),
            '--file='.$dumpPath,
            (string) ($connection['database'] ?? ''),
        ];

        try {
            if (@touch($dumpPath) === false) {
                throw new RuntimeException('Não foi possível criar o arquivo de backup no diretório configurado.');
            }
            @chmod($dumpPath, 0600);
            $this->run($command, ['PGPASSWORD' => (string) ($connection['password'] ?? '')]);
            @chmod($dumpPath, 0600);
            $validation = $this->validateDumpPath($dumpPath);

            $manifest = [
                'format_version' => 2,
                'filename' => $filename,
                'source' => 'generated',
                'created_at' => now()->toIso8601String(),
                'created_by_user_id' => $userId,
                'created_by_name' => $userName,
                'app_version' => (string) config('app.version'),
                'database_driver' => 'pgsql',
                'database_name' => (string) ($connection['database'] ?? ''),
                'database_server_version' => $this->serverVersion(),
                'sha256' => $validation['sha256'],
                'size_bytes' => $validation['size_bytes'],
                'verified_with_pg_restore' => true,
                'app_key_fingerprint' => $this->appKeyFingerprint(),
                'restore_note' => 'Para migração completa, restaure o dump e preserve a mesma APP_KEY da instalação de origem.',
            ];

            $this->writeManifest($manifestPath, $manifest);
            $this->prune();
            return $manifest;
        } catch (\Throwable $e) {
            @unlink($dumpPath);
            @unlink($manifestPath);
            throw $e;
        }
    }

    public function import(UploadedFile $upload, string $userId, string $userName): array
    {
        $this->assertSupportedDatabase();
        $this->ensureDirectory();

        if (! $upload->isValid()) {
            throw new RuntimeException('O upload do backup não foi concluído corretamente.');
        }
        $maxBytes = max(1024, (int) config('backup.upload_max_kb', 524288) * 1024);
        if (($upload->getSize() ?: 0) > $maxBytes) {
            throw new RuntimeException('O arquivo excede o limite configurado para importação de backups.');
        }

        $base = $this->newBaseName();
        $filename = $base.'.dump';
        $dumpPath = $this->backupPath().'/'.$filename;
        $manifestPath = $this->backupPath().'/'.$base.'.json';

        try {
            $upload->move($this->backupPath(), $filename);
            @chmod($dumpPath, 0600);
            $validation = $this->validateDumpPath($dumpPath);
            $manifest = [
                'format_version' => 2,
                'filename' => $filename,
                'source' => 'uploaded',
                'original_name' => basename((string) $upload->getClientOriginalName()),
                'created_at' => now()->toIso8601String(),
                'created_by_user_id' => $userId,
                'created_by_name' => $userName,
                'app_version' => 'origem não informada',
                'database_driver' => 'pgsql',
                'database_name' => 'origem não informada',
                'database_server_version' => 'origem não informada',
                'sha256' => $validation['sha256'],
                'size_bytes' => $validation['size_bytes'],
                'verified_with_pg_restore' => true,
                'app_key_fingerprint' => 'desconhecida',
                'restore_note' => 'Backup importado. Em migração entre VMs, confirme que a nova instalação usa a APP_KEY da instalação de origem antes de restaurar.',
            ];
            $this->writeManifest($manifestPath, $manifest);
            $this->prune();
            return $manifest;
        } catch (\Throwable $e) {
            @unlink($dumpPath);
            @unlink($manifestPath);
            throw $e;
        }
    }

    public function restoreCandidate(string $filename): array
    {
        $path = $this->file($filename);
        $manifest = $this->manifestFor($filename) ?? [];
        $validation = $this->validateDumpPath($path);
        if (! empty($manifest['sha256']) && ! hash_equals((string) $manifest['sha256'], (string) $validation['sha256'])) {
            throw new RuntimeException('O SHA-256 atual do arquivo não corresponde ao manifesto. O backup pode ter sido alterado.');
        }

        $sourceFingerprint = (string) ($manifest['app_key_fingerprint'] ?? 'desconhecida');
        $keyKnown = $sourceFingerprint !== '' && $sourceFingerprint !== 'desconhecida' && $sourceFingerprint !== 'ausente';
        $keyMatches = ! $keyKnown || hash_equals($sourceFingerprint, $this->appKeyFingerprint());

        return [
            'filename' => $filename,
            'manifest' => $manifest,
            'sha256' => $validation['sha256'],
            'size_bytes' => $validation['size_bytes'],
            'app_key_known' => $keyKnown,
            'app_key_matches' => $keyMatches,
            'current_app_key_fingerprint' => $this->appKeyFingerprint(),
            'source_app_key_fingerprint' => $sourceFingerprint,
        ];
    }

    public function queueRestore(string $filename, string $userId, string $userName, string $reason): string
    {
        $candidate = $this->restoreCandidate($filename);
        if ($candidate['app_key_known'] && ! $candidate['app_key_matches']) {
            throw new RuntimeException('A APP_KEY atual não corresponde ao backup selecionado. Para evitar perda de acesso a dados criptografados, configure primeiro a APP_KEY da instalação de origem.');
        }

        $this->ensureRestoreDirectories();
        $dispatcher = (string) config('backup.restore_dispatcher', '/usr/local/sbin/sgml-web-restore-dispatch');
        if (! is_executable($dispatcher)) {
            throw new RuntimeException('O despachante seguro de restauração não está instalado. Reaplique o hotfix RC56 como root.');
        }

        $requestId = 'r'.bin2hex(random_bytes(12));
        $requestPath = $this->restoreRequestPath().'/'.$requestId.'.json';
        $payload = [
            'request_id' => $requestId,
            'filename' => $filename,
            'requested_at' => now()->toIso8601String(),
            'requested_by_user_id' => $userId,
            'requested_by_name' => $userName,
            'reason' => $reason,
            'sha256' => $candidate['sha256'],
            'app_key_fingerprint' => $this->appKeyFingerprint(),
        ];
        file_put_contents($requestPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL, LOCK_EX);
        @chmod($requestPath, 0600);

        try {
            $this->run(['/usr/bin/sudo', '-n', $dispatcher, $requestId]);
        } catch (\Throwable $e) {
            @unlink($requestPath);
            throw new RuntimeException('Não foi possível agendar a restauração segura: '.$e->getMessage(), 0, $e);
        }

        return $requestId;
    }

    public function restoreStatus(?string $requestId): ?array
    {
        if (! is_string($requestId) || ! preg_match('/^r[a-f0-9]{24}$/', $requestId)) {
            return null;
        }
        $path = $this->restoreStatusPath().'/'.$requestId.'.json';
        if (! is_file($path)) {
            return ['request_id' => $requestId, 'state' => 'scheduled', 'message' => 'Restauração agendada. O SGML entrará em manutenção por alguns instantes.'];
        }
        $data = json_decode((string) @file_get_contents($path), true);
        return is_array($data) ? $data : null;
    }

    public function file(string $filename): string
    {
        if (! preg_match('/^sgml-db-\d{8}_\d{6}-[a-f0-9]{8}\.dump$/', $filename)) {
            throw new RuntimeException('Nome de backup inválido.');
        }
        $path = $this->backupPath().'/'.$filename;
        if (! is_file($path)) {
            throw new RuntimeException('Backup não encontrado.');
        }
        return $path;
    }

    public function delete(string $filename): void
    {
        $dump = $this->file($filename);
        $manifest = preg_replace('/\.dump$/', '.json', $dump);
        if (! @unlink($dump)) {
            throw new RuntimeException('Não foi possível excluir o arquivo de backup.');
        }
        if (is_string($manifest) && is_file($manifest)) {
            @unlink($manifest);
        }
    }

    public function manifestFor(string $filename): ?array
    {
        $dump = $this->file($filename);
        $manifestPath = preg_replace('/\.dump$/', '.json', $dump);
        if (! is_string($manifestPath) || ! is_file($manifestPath)) {
            return null;
        }
        $decoded = json_decode((string) file_get_contents($manifestPath), true);
        return is_array($decoded) ? $decoded : null;
    }

    private function validateDumpPath(string $dumpPath): array
    {
        if (! is_file($dumpPath) || (filesize($dumpPath) ?: 0) < 1024) {
            throw new RuntimeException('O arquivo de backup é inválido ou está vazio.');
        }
        $restoreOutput = $this->run([(string) config('backup.pg_restore', '/usr/bin/pg_restore'), '--list', $dumpPath]);
        if (! str_contains($restoreOutput, 'TABLE') && ! str_contains($restoreOutput, 'SCHEMA')) {
            throw new RuntimeException('pg_restore não reconheceu objetos esperados no backup.');
        }
        return [
            'sha256' => hash_file('sha256', $dumpPath),
            'size_bytes' => filesize($dumpPath) ?: 0,
        ];
    }

    private function writeManifest(string $manifestPath, array $manifest): void
    {
        file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL, LOCK_EX);
        @chmod($manifestPath, 0600);
    }

    private function newBaseName(): string
    {
        return 'sgml-db-'.now()->format('Ymd_His').'-'.bin2hex(random_bytes(4));
    }

    private function assertSupportedDatabase(): void
    {
        if (config('database.default') !== 'pgsql') {
            throw new RuntimeException('O backup administrativo suporta PostgreSQL, que é o banco oficial do SGML.');
        }
    }

    private function ensureDirectory(): void
    {
        $path = $this->backupPath();
        if (! is_dir($path) && ! @mkdir($path, 0700, true) && ! is_dir($path)) {
            throw new RuntimeException('Não foi possível criar o diretório de backups.');
        }
        @chmod($path, 0700);
    }

    private function ensureRestoreDirectories(): void
    {
        foreach ([$this->restoreRequestPath(), $this->restoreStatusPath()] as $path) {
            if (! is_dir($path) && ! @mkdir($path, 0700, true) && ! is_dir($path)) {
                throw new RuntimeException('Não foi possível preparar a área segura de restauração.');
            }
            @chmod($path, 0700);
        }
    }

    private function backupPath(): string
    {
        return rtrim((string) config('backup.path', '/var/backups/sgml/database'), '/');
    }

    private function restoreRequestPath(): string
    {
        return rtrim((string) config('backup.restore_request_path', '/var/lib/sgml/restore-requests'), '/');
    }

    private function restoreStatusPath(): string
    {
        return rtrim((string) config('backup.restore_status_path', '/var/lib/sgml/restore-status'), '/');
    }

    private function serverVersion(): string
    {
        try {
            $row = DB::selectOne('show server_version');
            return (string) ($row->server_version ?? 'desconhecida');
        } catch (\Throwable) {
            return 'desconhecida';
        }
    }

    private function appKeyFingerprint(): string
    {
        $key = (string) config('app.key', '');
        return $key === '' ? 'ausente' : substr(hash('sha256', $key), 0, 16);
    }

    private function run(array $command, array $extraEnv = []): string
    {
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $baseEnv = getenv();
        $env = array_merge(is_array($baseEnv) ? $baseEnv : [], $extraEnv);
        $process = proc_open($command, $descriptors, $pipes, null, $env, ['bypass_shell' => true]);
        if (! is_resource($process)) {
            throw new RuntimeException('Não foi possível iniciar o utilitário solicitado.');
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $stdout = '';
        $stderr = '';
        $status = ['exitcode' => -1];
        $deadline = microtime(true) + max(30, (int) config('backup.timeout', 300));

        do {
            $status = proc_get_status($process);
            $stdout .= stream_get_contents($pipes[1]);
            $stderr .= stream_get_contents($pipes[2]);
            if (! $status['running']) break;
            if (microtime(true) > $deadline) {
                proc_terminate($process);
                throw new RuntimeException('Tempo limite excedido ao executar utilitário de backup/restauração.');
            }
            usleep(100000);
        } while (true);

        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        $knownExit = (int) ($status['exitcode'] ?? -1);
        if ($exit !== 0 && $knownExit !== 0) {
            throw new RuntimeException(trim($stderr) ?: 'falha sem mensagem do utilitário');
        }
        return $stdout."\n".$stderr;
    }

    private function prune(): void
    {
        $max = max(1, (int) config('backup.max_files', 30));
        $items = $this->list();
        foreach (array_slice($items, $max) as $item) {
            try { $this->delete((string) $item['filename']); } catch (\Throwable) {}
        }
    }
}
