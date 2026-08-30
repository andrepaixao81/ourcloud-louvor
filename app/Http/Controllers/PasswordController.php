<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\OrganizationMailSettingsService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    public function request() { return view('auth.forgot-password'); }

    public function email(Request $request, OrganizationMailSettingsService $mailSettings)
    {
        $data = $request->validate(['email'=>['required','email']]);
        $user = User::query()->where('email', $data['email'])->where('active', true)->first();
        if ($user) {
            $mailSettings->apply($user->organization_id);
        }
        Password::sendResetLink($request->only('email'));
        return back()->with('status','Se o e-mail existir, as instruções de recuperação serão enviadas.');
    }

    public function reset(Request $request, string $token)
    {
        return view('auth.reset-password',['token'=>$token,'email'=>$request->email]);
    }

    public function update(Request $request)
    {
        $request->validate(['token'=>'required','email'=>'required|email','password'=>'required|min:12|confirmed']);
        $status = Password::reset($request->only('email','password','password_confirmation','token'), function(User $user,string $password){
            $user->forceFill([
                'password'=>Hash::make($password),
                'must_change_password' => false,
                'remember_token'=>Str::random(60),
            ])->save();
            event(new PasswordReset($user));
        });
        return $status === Password::PASSWORD_RESET ? redirect()->route('login')->with('status',__($status)) : back()->withErrors(['email'=>__($status)]);
    }

    public function required(Request $request)
    {
        abort_unless($request->user()?->must_change_password, 404);
        return view('auth.change-required');
    }

    public function requiredUpdate(Request $request)
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        if (Hash::check($data['password'], $request->user()->password)) {
            throw ValidationException::withMessages([
                'password' => 'A nova senha deve ser diferente da senha temporária.',
            ]);
        }

        $request->user()->forceFill([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
            'remember_token' => Str::random(60),
        ])->save();

        $request->session()->regenerate();

        return redirect()->route($request->user()->preferredHomeRouteName())->with('status', 'Senha definida com sucesso.');
    }
}
