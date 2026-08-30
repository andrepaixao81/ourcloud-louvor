@php
    $title = 'Muitas ações em pouco tempo';
    $user = auth()->user();
    $homeUrl = $user ? route($user->preferredHomeRouteName()) : route('login');
@endphp
@extends(auth()->check() ? 'layouts.app' : 'layouts.guest')

@section('content')
<div class="mx-auto max-w-3xl py-4 sm:py-8">
    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 bg-gradient-to-br from-amber-50 via-white to-orange-50 p-6 sm:p-8">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-amber-100 text-amber-700 ring-8 ring-amber-50">
                    <x-icon name="alert" class="h-7 w-7"/>
                </div>
                <div>
                    <div class="mb-2 inline-flex items-center rounded-full bg-white px-3 py-1 text-xs font-black uppercase tracking-wide text-amber-700 ring-1 ring-inset ring-amber-200">429 · proteção temporária</div>
                    <h1 class="text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">Esta ação foi repetida muitas vezes em pouco tempo</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">Nenhum dado foi alterado por esta tentativa. Aguarde alguns segundos e tente novamente. As operações administrativas do OurCloud Louvor possuem limites independentes para evitar bloqueios entre funções diferentes.</p>
                </div>
            </div>
        </div>
        <div class="p-6 sm:p-8">
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 p-4">
                    <h2 class="text-sm font-black text-slate-900">Por que isso aconteceu?</h2>
                    <p class="mt-1 text-xs leading-5 text-slate-500">O sistema limita repetições rápidas para proteger operações sensíveis, como limpeza, backup e administração.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 p-4">
                    <h2 class="text-sm font-black text-slate-900">O que fazer agora?</h2>
                    <p class="mt-1 text-xs leading-5 text-slate-500">Volte à tela anterior, aguarde alguns segundos e execute a ação apenas uma vez.</p>
                </div>
            </div>
            <div class="mt-7 flex flex-col-reverse gap-2 border-t border-slate-100 pt-6 sm:flex-row">
                <button type="button" class="sgml-btn-primary justify-center" onclick="history.back()">Voltar e tentar novamente</button>
                <a class="sgml-btn-secondary justify-center" href="{{ $homeUrl }}">Ir para meu início</a>
            </div>
        </div>
    </section>
</div>
@endsection
