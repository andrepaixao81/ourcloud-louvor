<?php

namespace App\Http\Controllers;

use App\Services\OrganizationMailSettingsService;
use App\Services\UserPersonProfileService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function show(Request $request, UserPersonProfileService $profiles)
    {
        $user = $request->user()->load(['roles', 'person']);
        if (! $user->person) {
            $profiles->ensure($user);
            $user->load('person');
        }

        return view('account.show', [
            'user' => $user,
            'phoneDisplay' => $this->formatBrazilianPhone((string) ($user->person?->phone ?? '')),
            'birthDateDisplay' => $user->person?->birth_date?->format('d/m/Y'),
        ]);
    }

    public function updateEmail(Request $request, UserPersonProfileService $profiles)
    {
        $user = $request->user();
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => ['required', 'string'],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'A senha atual não confere.']);
        }

        $person = $profiles->ensure($user);
        $email = Str::lower($data['email']);
        DB::transaction(function () use ($user, $person, $email): void {
            $user->forceFill(['email' => $email])->save();
            $person->forceFill(['email' => $email])->save();
        });
        return back()->with('status', 'E-mail de acesso atualizado.');
    }

    public function updatePhone(Request $request, UserPersonProfileService $profiles)
    {
        $user = $request->user()->load('person');
        $person = $user->person ?: $profiles->ensure($user);

        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $raw = trim((string) ($data['phone'] ?? ''));
        if ($raw === '') {
            $person->update(['phone' => null]);
            return back()->with('status', 'Telefone removido da sua conta.');
        }

        $digits = preg_replace('/\D+/', '', $raw) ?: '';

        // A conta guarda DDD + número brasileiro. Se o usuário colar +55, o DDI é
        // removido antes de salvar; no envio do WhatsApp o SGML acrescenta 55.
        if (in_array(strlen($digits), [12, 13], true) && str_starts_with($digits, '55')) {
            $digits = substr($digits, 2);
        }

        if (! in_array(strlen($digits), [10, 11], true)) {
            throw ValidationException::withMessages([
                'phone' => 'Informe DDD + número do telefone. Ex.: (21) 99999-9999.',
            ]);
        }

        if ((int) substr($digits, 0, 2) < 11) {
            throw ValidationException::withMessages([
                'phone' => 'Informe um DDD válido junto com o número do telefone.',
            ]);
        }

        $person->update(['phone' => $digits]);

        return back()->with('status', 'Telefone/WhatsApp atualizado. Os próximos avisos usarão este número quando o WhatsApp estiver habilitado.');
    }

    public function updateBirthDate(Request $request, UserPersonProfileService $profiles)
    {
        $user = $request->user()->load('person');
        $person = $user->person ?: $profiles->ensure($user);

        $data = $request->validate([
            'birth_date' => ['nullable', 'date', 'after_or_equal:1900-01-01', 'before_or_equal:today'],
        ]);

        $person->update(['birth_date' => $data['birth_date'] ?: null]);

        return back()->with('status', $data['birth_date'] ? 'Data de nascimento atualizada.' : 'Data de nascimento removida.');
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'A senha atual não confere.']);
        }
        if (Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => 'A nova senha deve ser diferente da senha atual.']);
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
            'remember_token' => Str::random(60),
        ])->save();
        $request->session()->regenerate();

        return back()->with('status', 'Senha alterada com sucesso.');
    }

    public function sendPasswordReset(Request $request, OrganizationMailSettingsService $mailSettings)
    {
        $user = $request->user();
        if (! $mailSettings->isConfigured($user->organization_id)) {
            throw ValidationException::withMessages(['email' => 'O SMTP da organização ainda não está configurado.']);
        }

        $mailSettings->apply($user->organization_id);
        Password::sendResetLink(['email' => $user->email]);
        return back()->with('status', 'Enviamos um link de redefinição para '.$user->email.'.');
    }

    private function formatBrazilianPhone(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?: '';
        if (in_array(strlen($digits), [12, 13], true) && str_starts_with($digits, '55')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 11) {
            return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 5), substr($digits, 7, 4));
        }
        if (strlen($digits) === 10) {
            return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 4), substr($digits, 6, 4));
        }

        return $raw;
    }
}
