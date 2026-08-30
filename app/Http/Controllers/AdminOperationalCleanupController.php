<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Services\DatabaseBackupService;
use App\Services\OperationalCleanupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminOperationalCleanupController extends Controller
{
    public function index(Request $request, OperationalCleanupService $cleanup)
    {
        $organizationId = (string) $request->user()->organization_id;

        return view('admin.operational-cleanup', [
            'preview' => $cleanup->preview($organizationId),
        ]);
    }

    public function destroy(
        Request $request,
        OperationalCleanupService $cleanup,
        DatabaseBackupService $backups,
        AuditService $audit,
    ): RedirectResponse {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'password' => ['required', 'string'],
            'confirmation' => ['required', 'in:LIMPAR TESTES'],
            'remove_manual_services' => ['nullable', 'boolean'],
            'remove_special_events' => ['nullable', 'boolean'],
            'regenerate_recurring_services' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        if (! is_string($user->password) || $user->password === '' || ! Hash::check($data['password'], $user->password)) {
            return back()->with('error', 'Senha atual do Administrador inválida. Nenhum dado foi alterado.');
        }

        try {
            // Uma limpeza operacional é destrutiva por definição. O sistema cria
            // automaticamente um ponto de retorno antes de apagar qualquer dado.
            $backup = $backups->create((string) $user->id, (string) $user->name);
            $result = $cleanup->cleanup(
                (string) $user->organization_id,
                $request->boolean('remove_manual_services'),
                $request->boolean('remove_special_events'),
                $request->boolean('regenerate_recurring_services'),
            );

            $audit->event('administration.operational_cleanup.completed', null, (string) $data['reason'], [
                'backup_filename' => $backup['filename'] ?? null,
                'before' => $result['before'],
                'generated_services' => $result['generated_services'],
                'removed_manual_services' => $result['removed_manual_services'],
                'remove_special_events' => $request->boolean('remove_special_events'),
                'removed_special_events' => $result['removed_special_events'] ?? 0,
                'regenerated_recurring_services' => $request->boolean('regenerate_recurring_services'),
                'after' => $result['after'],
            ]);

            $message = 'Dados de teste removidos com sucesso. Um backup automático foi criado antes da limpeza: '.($backup['filename'] ?? 'backup concluído').'. As regras dos cultos foram preservadas e as ocorrências recorrentes materializadas foram removidas.';
            if (($result['generated_services'] ?? 0) > 0) {
                $message .= ' Foram recriadas '.($result['generated_services']).' ocorrência(s) recorrente(s) futura(s) porque essa opção foi selecionada.';
            } else {
                $message .= ' Elas permanecerão zeradas até você gerar uma nova escala ou usar Cultos > Gerar recorrências.';
            }
            if (! empty($result['warnings'])) {
                $message .= ' Atenção: '.implode(' ', $result['warnings']);
            }

            return redirect()->route('admin.operational-cleanup.index')->with('success', $message);
        } catch (\Throwable $exception) {
            report($exception);
            return back()->with('error', 'A limpeza não foi concluída: '.$exception->getMessage());
        }
    }
}
