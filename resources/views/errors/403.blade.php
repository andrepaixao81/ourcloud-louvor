@php
    $title = 'Acesso não disponível';
    $user = auth()->user();
    $actionContext = session('action_item_context');
    $fromActionCenter = is_array($actionContext) && ($actionContext['from_actions'] ?? false);
    $participantOnly = $user?->isParticipantOnly() ?? false;
    $homeUrl = $user ? route($user->preferredHomeRouteName()) : route('login');
@endphp
@extends(auth()->check() ? 'layouts.app' : 'layouts.guest')

@section('content')
<div class="mx-auto max-w-3xl py-4 sm:py-8">
    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 bg-gradient-to-br from-indigo-50 via-white to-sky-50 p-6 sm:p-8">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-indigo-100 text-indigo-700 ring-8 ring-indigo-50">
                    <x-icon name="shield" class="h-7 w-7"/>
                </div>
                <div class="min-w-0">
                    <div class="mb-2 inline-flex items-center rounded-full bg-white px-3 py-1 text-xs font-black uppercase tracking-wide text-indigo-700 ring-1 ring-inset ring-indigo-200">403 · acesso restrito</div>
                    <h1 class="text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">
                        {{ $fromActionCenter ? 'Este item não está mais disponível para o seu perfil' : 'Você não tem acesso a esta área' }}
                    </h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">
                        @if($fromActionCenter)
                            O aviso continua no seu histórico da Central de Ações, mas a sua função ou as permissões atuais mudaram desde que ele foi criado. Por segurança, o sistema não abre conteúdos que deixaram de fazer parte do seu acesso.
                        @elseif($participantOnly)
                            Seu perfil atual é <strong>Participante do Louvor</strong>. Esta área exige uma permissão diferente daquelas disponíveis para sua função atual.
                        @else
                            Esta área exige uma permissão que não está disponível no seu perfil atual. Nenhum dado foi alterado.
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <div class="p-6 sm:p-8">
            @if($fromActionCenter && filled($actionContext['title'] ?? null))
                <div class="mb-6 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:p-5">
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white text-slate-500 shadow-sm"><x-icon name="bell" class="h-4 w-4"/></div>
                        <div class="min-w-0">
                            <div class="text-xs font-black uppercase tracking-wide text-slate-400">Aviso que você tentou abrir</div>
                            <div class="mt-1 font-black text-slate-900">{{ $actionContext['title'] }}</div>
                            @if(filled($actionContext['message'] ?? null))<p class="mt-1 text-sm leading-relaxed text-slate-600">{{ $actionContext['message'] }}</p>@endif
                        </div>
                    </div>
                </div>
            @endif

            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 p-4">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700"><x-icon name="check" class="h-4 w-4"/></div>
                    <h2 class="mt-3 text-sm font-black text-slate-900">Seus dados estão seguros</h2>
                    <p class="mt-1 text-xs leading-5 text-slate-500">O bloqueio apenas impede a visualização de uma área sem permissão.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 p-4">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-sky-50 text-sky-700"><x-icon name="user" class="h-4 w-4"/></div>
                    <h2 class="mt-3 text-sm font-black text-slate-900">Perfis podem mudar</h2>
                    <p class="mt-1 text-xs leading-5 text-slate-500">Uma função anterior pode ter criado avisos que já não pertencem ao seu acesso atual.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 p-4">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-50 text-amber-700"><x-icon name="info" class="h-4 w-4"/></div>
                    <h2 class="mt-3 text-sm font-black text-slate-900">Precisa acessar?</h2>
                    <p class="mt-1 text-xs leading-5 text-slate-500">Se você acredita que deveria ter acesso, procure o Administrador ou Responsável pelo Ministério.</p>
                </div>
            </div>

            <div class="mt-7 flex flex-col-reverse gap-2 border-t border-slate-100 pt-6 sm:flex-row sm:flex-wrap sm:items-center">
                @auth
                    <a class="sgml-btn-primary justify-center" href="{{ route('actions.index') }}"><x-icon name="bell" class="h-4 w-4"/>Voltar à Central de Ações</a>
                    <a class="sgml-btn-secondary justify-center" href="{{ $homeUrl }}"><x-icon name="home" class="h-4 w-4"/>Ir para meu início</a>
                    @if($fromActionCenter && filled($actionContext['id'] ?? null))
                        <form method="POST" action="{{ route('actions.dismiss', ['item' => $actionContext['id']]) }}" class="sm:ml-auto">
                            @csrf
                            <button type="submit" class="sgml-btn-tertiary w-full justify-center text-slate-500 sm:w-auto">Dispensar este aviso</button>
                        </form>
                    @endif
                @else
                    <a class="sgml-btn-primary justify-center" href="{{ route('login') }}">Voltar ao acesso</a>
                @endauth
            </div>
        </div>
    </section>
</div>
@endsection
