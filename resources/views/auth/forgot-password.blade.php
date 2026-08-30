@extends('layouts.guest')
@section('content')
<h1 class="text-xl font-bold">Recuperar senha</h1><p class="mt-1 text-sm text-slate-500">Informe o e-mail cadastrado.</p>
<form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">@csrf
<div><label class="sgml-label">E-mail</label><input class="sgml-input" type="email" name="email" value="{{ old('email') }}" required autofocus></div>
<button class="sgml-btn-primary w-full">Enviar instruções</button></form>
<a class="mt-4 block text-center text-sm font-semibold text-indigo-600" href="{{ route('login') }}">Voltar ao login</a>
@endsection
