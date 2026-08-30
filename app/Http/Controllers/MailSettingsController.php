<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Services\OrganizationMailSettingsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MailSettingsController extends Controller
{
    public function index(Request $request, OrganizationMailSettingsService $mailSettings)
    {
        return view('admin.mail', [
            'settings' => $mailSettings->get($request->user()->organization_id),
        ]);
    }

    public function update(Request $request, OrganizationMailSettingsService $mailSettings, AuditService $audit)
    {
        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:1000'],
            'security' => ['required', Rule::in(['smtp', 'smtps'])],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
            'ehlo_domain' => ['nullable', 'string', 'max:255'],
        ]);

        $data['enabled'] = $request->boolean('enabled');
        $mailSettings->save($request->user()->organization_id, $data);
        $audit->event('smtp.settings.updated', null, null, [
            'enabled' => $data['enabled'],
            'host' => $data['host'],
            'port' => $data['port'],
            'security' => $data['security'],
            'from_address' => $data['from_address'],
        ]);

        return back()->with('status', 'Configuração de SMTP salva. Use o teste antes de habilitar o envio de senhas.');
    }

    public function test(Request $request, OrganizationMailSettingsService $mailSettings, AuditService $audit)
    {
        $data = $request->validate(['recipient' => ['required', 'email', 'max:255']]);

        try {
            $mailSettings->sendTest($request->user()->organization_id, $data['recipient']);
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['smtp_test' => 'Falha no teste SMTP: '.$e->getMessage()]);
        }

        $audit->event('smtp.test.sent', null, null, ['recipient' => $data['recipient']]);

        return back()->with('status', 'E-mail de teste enviado para '.$data['recipient'].'.');
    }
}
