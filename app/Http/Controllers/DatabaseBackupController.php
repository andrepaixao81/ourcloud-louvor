<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Services\DatabaseBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DatabaseBackupController extends Controller
{
    public function index(Request $request, DatabaseBackupService $backups)
    {
        $restoreCandidate = null;
        $restoreFilename = $request->string('restore')->toString();
        if ($restoreFilename !== '') {
            try {
                $restoreCandidate = $backups->restoreCandidate($restoreFilename);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return view('admin.backups', [
            'backups' => $backups->list(),
            'diagnostics' => $backups->diagnostics(),
            'restoreCandidate' => $restoreCandidate,
            'restoreStatus' => $backups->restoreStatus($request->string('restore_request')->toString()),
        ]);
    }

    public function store(Request $request, DatabaseBackupService $backups, AuditService $audit): RedirectResponse
    {
        $user = $request->user();
        try {
            $manifest = $backups->create((string) $user->id, (string) $user->name);
            $audit->event('database.backup.created', null, null, [
                'filename' => $manifest['filename'],
                'sha256' => $manifest['sha256'],
                'size_bytes' => $manifest['size_bytes'],
                'app_version' => $manifest['app_version'],
                'database_name' => $manifest['database_name'],
            ]);
            return redirect()->route('admin.backups.index')->with('success', 'Backup do PostgreSQL criado e validado com sucesso.');
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('admin.backups.index')->with('error', 'Não foi possível gerar o backup: '.$e->getMessage());
        }
    }

    public function import(Request $request, DatabaseBackupService $backups, AuditService $audit): RedirectResponse
    {
        $maxKb = max(1024, (int) config('backup.upload_max_kb', 524288));
        $data = $request->validate([
            'backup_file' => ['required', 'file', 'max:'.$maxKb],
        ]);
        $user = $request->user();

        try {
            $manifest = $backups->import($data['backup_file'], (string) $user->id, (string) $user->name);
            $audit->event('database.backup.imported', null, null, [
                'filename' => $manifest['filename'],
                'original_name' => $manifest['original_name'] ?? null,
                'sha256' => $manifest['sha256'],
                'size_bytes' => $manifest['size_bytes'],
            ]);
            return redirect()->route('admin.backups.index')->with('success', 'Arquivo de backup importado e validado com pg_restore.');
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('admin.backups.index')->with('error', 'Não foi possível importar o backup: '.$e->getMessage());
        }
    }

    public function restore(Request $request, string $filename, DatabaseBackupService $backups, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'password' => ['required', 'string'],
            'confirmation' => ['required', 'in:RESTAURAR'],
            'app_key_ack' => ['accepted'],
        ]);
        $user = $request->user();
        if (! is_string($user?->password) || $user->password === '' || ! Hash::check($data['password'], $user->password)) {
            return redirect()->route('admin.backups.index', ['restore' => $filename])
                ->with('error', 'Senha atual do Administrador inválida. A restauração não foi agendada.');
        }

        try {
            $candidate = $backups->restoreCandidate($filename);
            $audit->event('database.restore.requested', null, (string) $data['reason'], [
                'filename' => $filename,
                'sha256' => $candidate['sha256'],
                'source_app_key_fingerprint' => $candidate['source_app_key_fingerprint'],
                'current_app_key_fingerprint' => $candidate['current_app_key_fingerprint'],
            ]);
            $requestId = $backups->queueRestore($filename, (string) $user->id, (string) $user->name, (string) $data['reason']);

            return redirect()->route('admin.backups.index', ['restore_request' => $requestId])
                ->with('success', 'Restauração agendada. Em alguns segundos o SGML entrará em manutenção, restaurará o banco e voltará automaticamente.');
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('admin.backups.index', ['restore' => $filename])
                ->with('error', 'Não foi possível agendar a restauração: '.$e->getMessage());
        }
    }

    public function download(Request $request, string $filename, DatabaseBackupService $backups, AuditService $audit): BinaryFileResponse
    {
        $path = $backups->file($filename);
        $manifest = $backups->manifestFor($filename);
        $audit->event('database.backup.downloaded', null, null, [
            'filename' => $filename,
            'sha256' => $manifest['sha256'] ?? null,
        ]);

        return response()->download($path, $filename, [
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(Request $request, string $filename, DatabaseBackupService $backups, AuditService $audit): RedirectResponse
    {
        try {
            $manifest = $backups->manifestFor($filename);
            $backups->delete($filename);
            $audit->event('database.backup.deleted', null, $request->string('reason')->toString() ?: 'Exclusão administrativa de backup', [
                'filename' => $filename,
                'sha256' => $manifest['sha256'] ?? null,
            ]);
            return redirect()->route('admin.backups.index')->with('success', 'Backup excluído do servidor.');
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('admin.backups.index')->with('error', 'Não foi possível excluir o backup: '.$e->getMessage());
        }
    }
}
