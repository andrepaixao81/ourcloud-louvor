<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function create() { return view('auth.login'); }

    public function store(Request $request)
    {
        $credentials = $request->validate(['email'=>['required','email'],'password'=>['required','string']]);
        $key = Str::lower($credentials['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email'=>'Muitas tentativas. Aguarde '.RateLimiter::availableIn($key).' segundos.']);
        }
        if (!Auth::attempt(['email'=>$credentials['email'],'password'=>$credentials['password'],'active'=>true], $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email'=>'Credenciais inválidas ou usuário inativo.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();

        if ($request->user()?->must_change_password) {
            return redirect()->route('password.required');
        }

        // Não usamos redirect()->intended aqui: se a pessoa abriu / ou uma
        // rota administrativa antes de autenticar, a URL pretendida pode exigir
        // uma permissão que seu papel não possui e resultar em 403 logo após o login.
        return redirect()->route($request->user()->preferredHomeRouteName());
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
