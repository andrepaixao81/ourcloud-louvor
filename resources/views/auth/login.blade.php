@extends('layouts.guest')
@section('content')
<div class="mb-5">
    <h1 class="text-xl font-black text-slate-900">Entrar</h1>
    <p class="mt-1 text-sm text-slate-600">Acesse o OurCloud Louvor com sua conta.</p>
</div>
<form method="POST" action="{{ route('login.store') }}" class="space-y-4">@csrf
<div><label class="sgml-label">E-mail</label><input class="sgml-input" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"></div>
<div><label class="sgml-label">Senha</label><input class="sgml-input" type="password" name="password" required autocomplete="current-password"></div>
<label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="remember" value="1"> Manter conectado</label>
<button class="sgml-btn-primary w-full">Entrar</button>
</form>
<a class="mt-4 block text-center text-sm font-semibold text-indigo-700" href="{{ route('password.request') }}">Esqueci minha senha</a>
@endsection
