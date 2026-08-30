<?php

namespace App\Http\Controllers;

use App\Models\CalendarIntegration;
use App\Services\AuditService;
use App\Services\GoogleCalendarService;
use App\Services\OrganizationGoogleCalendarSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class GoogleIntegrationController extends Controller
{
    public function index(Request $request, OrganizationGoogleCalendarSettingsService $settings)
    {
        return view('admin.integrations', [
            'integrations'=>CalendarIntegration::query()->where('provider','google')->get(),
            'googleSettings'=>$settings->get($request->user()->organization_id),
            'recommendedRedirect'=>rtrim((string) config('app.url'), '/').'/integrations/google/callback',
        ]);
    }

    public function updateSettings(Request $request, OrganizationGoogleCalendarSettingsService $settings, AuditService $audit)
    {
        $data = $request->validate([
            'enabled' => ['nullable','boolean'],
            'client_id' => ['nullable','string','max:500'],
            'client_secret' => ['nullable','string','max:1000'],
            'redirect_uri' => ['required','url','max:1000'],
            'calendar_id' => ['required','string','max:500'],
        ]);
        $data['enabled'] = $request->boolean('enabled');
        $settings->save($request->user()->organization_id, $data);
        $audit->event('google.settings.updated', null, null, ['enabled'=>$data['enabled'],'calendar_id'=>$data['calendar_id'],'redirect_uri'=>$data['redirect_uri']]);
        return back()->with('status','Configuração do Google Agenda salva na plataforma.');
    }

    public function connect(Request $request, GoogleCalendarService $google, OrganizationGoogleCalendarSettingsService $settings)
    {
        abort_unless($settings->configured($request->user()->organization_id),422,'Configure e habilite o Google Agenda nesta tela antes de conectar.');
        $data=$request->validate(['type'=>'required|in:organization,personal']);
        $state=Str::random(64);
        session(['google_oauth_state'=>$state,'google_oauth_type'=>$data['type']]);
        try {
            return redirect()->away($google->authorizationUrl($state, $request->user()->organization_id));
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['google'=>$e->getMessage()]);
        }
    }

    public function connectPersonal(Request $request, GoogleCalendarService $google, OrganizationGoogleCalendarSettingsService $settings)
    {
        abort_unless($settings->configured($request->user()->organization_id), 422, 'A integração Google Agenda precisa ser habilitada pela administração antes do uso pessoal.');
        $data = $request->validate(['scope' => ['required', 'in:personal,team']]);
        $existing = CalendarIntegration::query()
            ->where('provider', 'google')
            ->whereIn('integration_type', ['personal', 'team'])
            ->where('user_id', $request->user()->id)
            ->where('status', 'connected')
            ->whereNull('revoked_at')
            ->first();
        if ($existing) {
            $label = $existing->integration_type === 'team' ? 'agenda completa' : 'minha escala';
            throw ValidationException::withMessages(['google' => 'Já existe sincronização direta ativa para '.$label.'. Desconecte-a antes de trocar o modo.']);
        }
        $state = Str::random(64);
        session(['google_oauth_state' => $state, 'google_oauth_type' => $data['scope']]);
        try {
            return redirect()->away($google->authorizationUrl($state, $request->user()->organization_id));
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['google' => $e->getMessage()]);
        }
    }

    public function callback(Request $request, GoogleCalendarService $google, AuditService $audit)
    {
        abort_unless(hash_equals((string)session('google_oauth_state'),(string)$request->state),419);
        abort_if($request->filled('error'),422,'Autorização Google não concluída.');
        try {
            $integration=$google->connectFromCode((string)$request->code,(string)session('google_oauth_type','organization'));
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['google'=>$e->getMessage()]);
        }
        session()->forget(['google_oauth_state','google_oauth_type']);
        $audit->event('google.integration.connected',$integration);
        if (in_array($integration->integration_type, ['personal', 'team'], true)) {
            $label = $integration->integration_type === 'team' ? 'agenda completa' : 'agenda pessoal';
            return redirect()->route('my.agenda')->with('status','Google Agenda conectado para '.$label.'. A sincronização direta está ativa.');
        }
        return redirect()->route('integrations.index')->with('status','Google Agenda conectado.');
    }

    public function disconnectPersonal(Request $request, CalendarIntegration $integration, GoogleCalendarService $google, AuditService $audit)
    {
        abort_unless(in_array($integration->integration_type, ['personal', 'team'], true) && $integration->user_id === $request->user()->id, 403);
        try {
            $google->disconnectIntegration($integration);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['google' => $e->getMessage()]);
        }
        $integration->refresh();
        $audit->event('google.integration.revoked',$integration);
        return back()->with('status','Sincronização direta do seu Google Agenda desconectada e eventos projetados pelo SGML removidos.');
    }

    public function disconnect(CalendarIntegration $integration, GoogleCalendarService $google, AuditService $audit)
    {
        try {
            $google->disconnectIntegration($integration);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['google' => $e->getMessage()]);
        }
        $integration->refresh();
        $audit->event('google.integration.revoked',$integration);
        return back()->with('status','Integração desconectada e eventos projetados pelo SGML removidos.');
    }
}
