@extends('layouts.app')
@section('content')
<div class="mx-auto max-w-xl">
    <div class="sgml-card p-6">
        <div class="mb-5">
            <h1 class="text-2xl font-black">Defina sua nova senha</h1>
            <p class="mt-1 text-sm text-slate-500">Por segurança, a senha temporária deve ser substituída antes de continuar usando o SGML.</p>
        </div>
        <form method="POST" action="{{ route('password.required.update') }}" class="space-y-4">
            @csrf
            <div><label class="sgml-label">Nova senha</label><input class="sgml-input" type="password" name="password" minlength="12" autocomplete="new-password" required><p class="mt-1 text-xs text-slate-500">Use pelo menos 12 caracteres.</p></div>
            <div><label class="sgml-label">Confirmar nova senha</label><input class="sgml-input" type="password" name="password_confirmation" minlength="12" autocomplete="new-password" required></div>
            <button class="sgml-btn-primary">Salvar nova senha</button>
        </form>
    </div>
</div>
@endsection
