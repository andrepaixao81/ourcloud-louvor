@extends('layouts.guest')
@section('content')
<h1 class="text-xl font-bold">Definir nova senha</h1>
<form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4">@csrf
<input type="hidden" name="token" value="{{ $token }}">
<div><label class="sgml-label">E-mail</label><input class="sgml-input" type="email" name="email" value="{{ old('email',$email) }}" required></div>
<div><label class="sgml-label">Nova senha</label><input class="sgml-input" type="password" name="password" minlength="12" required></div>
<div><label class="sgml-label">Confirmar senha</label><input class="sgml-input" type="password" name="password_confirmation" minlength="12" required></div>
<button class="sgml-btn-primary w-full">Alterar senha</button></form>
@endsection
