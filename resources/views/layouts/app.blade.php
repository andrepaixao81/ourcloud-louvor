<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#4f46e5">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <?php
        $rawPageTitle = $title ?? null;
        $safePageTitle = is_scalar($rawPageTitle) && trim((string) $rawPageTitle) !== '' ? (string) $rawPageTitle : 'OurCloud Louvor';
        $rawFlashStatus = session('status');
        $safeFlashStatus = is_array($rawFlashStatus)
            ? collect($rawFlashStatus)->flatten()->filter(fn ($value) => is_scalar($value))->map(fn ($value) => (string) $value)->implode(' ')
            : (is_scalar($rawFlashStatus) ? (string) $rawFlashStatus : '');
    ?>
    <title>{{ $safePageTitle }} - OurCloud Louvor</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
    @livewireStyles
</head>
<body>
<?php
    $currentUser = auth()->user();
    $assistanceContext = app(\App\Services\MinisterAssistanceContext::class);
    $assistedMinister = $assistanceContext->assistedPerson($currentUser);
    $realActor = $assistanceContext->actorUser($currentUser);
    $assistanceActive = $assistedMinister !== null;
    $homeRoute = route($currentUser->preferredHomeRouteName());
    $ministerOnlyNavigation = $currentUser->usesMinisterOnlyNavigation();
    $canUseActionCenter = ! $ministerOnlyNavigation;
    $canSeePersonalAgenda = $currentUser->hasPermission('agenda.view') && ! $currentUser->isParticipantOnly() && ! $currentUser->isMemberWithoutMinistryAccess();
    $canSeeSpecialEvents = $currentUser->canSeeSpecialEvents();
    $canSeeBirthdays = $currentUser->canViewBirthdays();
    $canManageServices = collect(['services.create', 'services.update', 'services.cancel', 'services.delete', 'services.restore'])
        ->contains(fn ($permission) => $currentUser->hasPermission($permission));
    $swapAlertCount = app(\App\Services\SwapAlertService::class)->pendingCount($currentUser, $assistanceContext->person($currentUser));
    $hasAnyModule = collect([
        'reports.dashboard', 'agenda.view', 'calendar.view', 'schedule.view', 'special_events.view',
        'services.view', 'songs.view', 'users.view', 'administration.settings',
        'administration.audit', 'administration.integrations', 'rehearsals.view', 'song_history.view', 'birthdays.view',
    ])->contains(fn ($permission) => $currentUser->hasPermission($permission));
    $canSeeSongs = $currentUser->hasPermission('songs.view') || $currentUser->hasPermission('song_history.view');
    $canSwap = $currentUser->hasPermission('schedule.swap');
    $canSeeAdministration = $currentUser->hasPermission('administration.settings')
        || $currentUser->hasPermission('users.permissions')
        || $currentUser->hasPermission('administration.audit')
        || $currentUser->hasPermission('administration.integrations')
        || $currentUser->hasPermission('administration.whatsapp');
    $navLink = fn (bool $active) => 'sgml-sidebar-link '.($active ? 'sgml-sidebar-link-active' : '');
?>

<div class="sgml-app-shell {{ $ministerOnlyNavigation ? 'lg:!block' : '' }}">
    @if(! $ministerOnlyNavigation)
    <aside class="sgml-sidebar" aria-label="Navegação principal">
        <div class="sgml-sidebar-inner">
            <a href="{{ $homeRoute }}" class="sgml-sidebar-brand">
                <img src="/brand/ourcloud-louvor.svg" alt="" class="h-10 w-10 rounded-2xl shadow-sm">
                <div class="min-w-0">
                    <div class="truncate text-base font-black tracking-tight"><span class="text-indigo-700">OurCloud</span> Louvor</div>
                    <div class="truncate text-[11px] font-medium text-slate-400">Ministério de Louvor</div>
                </div>
            </a>

            <nav class="flex-1" aria-label="Módulos">
                <div class="sgml-sidebar-group">
                    <div class="sgml-sidebar-label">Visão geral</div>
                    @if($currentUser->hasPermission('reports.dashboard'))
                        <a class="{{ $navLink(request()->routeIs('dashboard')) }}" href="{{ route('dashboard') }}"><x-icon name="home"/>Visão geral</a>
                    @endif
                    @if($canSeePersonalAgenda)
                        <a class="{{ $navLink(request()->routeIs('my.agenda','availability.*')) }}" href="{{ route('my.agenda') }}"><x-icon name="calendar"/>Minha Agenda</a>
                    @endif
                    @if($currentUser->hasPermission('calendar.view'))
                        <a class="{{ $navLink(request()->routeIs('calendar.index','calendar.service-detail','calendar.events.*')) }}" href="{{ route('calendar.index') }}"><x-icon name="calendar"/>Agenda do Ministério</a>
                    @endif
                </div>

                <div class="sgml-sidebar-group">
                    <div class="sgml-sidebar-label">Ministério</div>
                    @if($currentUser->hasPermission('schedule.view'))
                        <a class="{{ $navLink(request()->routeIs('schedule.*')) }}" href="{{ route('schedule.index') }}"><x-icon name="clipboard"/>Escalas</a>
                    @endif
                    @if($canManageServices)
                        <a class="{{ $navLink(request()->routeIs('services.index','services.create','services.show','services.edit','services.month-template*')) }}" href="{{ route('services.index') }}"><x-icon name="book"/>Cultos</a>
                    @endif
                    @if($canSeeSongs)
                        <a class="{{ $navLink(request()->routeIs('songs.*')) }}" href="{{ $currentUser->hasPermission('songs.view') ? route('songs.index') : route('songs.history') }}"><x-icon name="music"/>Biblioteca de Louvores</a>
                    @endif
                    <a class="{{ $navLink(request()->routeIs('programmed-songs.*')) }}" href="{{ route('programmed-songs.index') }}"><x-icon name="music"/>Louvores programados</a>
                    @if($currentUser->hasPermission('rehearsals.view'))
                        <a class="{{ $navLink(request()->routeIs('rehearsals.*')) }}" href="{{ route('rehearsals.index') }}"><x-icon name="mic"/>Ensaios</a>
                    @endif
                    @if($currentUser->hasPermission('schedule.swap'))
                        <a class="{{ $navLink(request()->routeIs('swaps.*')) }}" href="{{ route('swaps.index') }}"><x-icon name="repeat"/><span class="flex-1">Trocas</span>@if($swapAlertCount > 0)<span class="rounded-full bg-rose-600 px-2 py-0.5 text-[10px] font-black text-white">{{ $swapAlertCount > 99 ? '99+' : $swapAlertCount }}</span>@endif</a>
                    @endif
                </div>

                @if($currentUser->hasPermission('users.view') || $canSeeBirthdays)
                <div class="sgml-sidebar-group">
                    <div class="sgml-sidebar-label">Pessoas</div>
                    @if($currentUser->hasPermission('users.view'))<a class="{{ $navLink(request()->routeIs('people.*')) }}" href="{{ route('people.index') }}"><x-icon name="users"/>Pessoas</a>@endif
                    @if($canSeeBirthdays)<a class="{{ $navLink(request()->routeIs('birthdays.*')) }}" href="{{ route('birthdays.index') }}"><x-icon name="cake"/>Aniversariantes</a>@endif
                </div>
                @endif

                @if($canSeeSpecialEvents)
                <div class="sgml-sidebar-group">
                    <div class="sgml-sidebar-label">Planejamento</div>
                    <a class="{{ $navLink(request()->routeIs('special-events.*')) }}" href="{{ route('special-events.index') }}"><x-icon name="sparkles"/>Eventos especiais</a>
                </div>
                @endif

                @if($currentUser->hasPermission('reports.dashboard') || $canSeeAdministration)
                <div class="sgml-sidebar-group">
                    <div class="sgml-sidebar-label">Gestão</div>
                    @if($currentUser->hasPermission('reports.dashboard'))<a class="{{ $navLink(request()->routeIs('reports.*')) }}" href="{{ route('reports.index') }}"><x-icon name="chart"/>Relatórios</a>@endif
                    @if($canSeeAdministration)<a class="{{ $navLink(request()->routeIs('admin.*','congregations.*','service-types.*','services.recurring*','audit.*','integrations.*')) }}" href="{{ route('admin.index') }}"><x-icon name="settings"/>Administração</a>@endif
                </div>
                @endif
            </nav>

            <div class="mt-5 border-t border-slate-100 pt-4">
                @if(! $assistanceActive)
                    <a href="{{ route('account.show') }}" class="flex items-center gap-3 rounded-2xl p-2.5 transition hover:bg-slate-50">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 font-black text-indigo-700">{{ mb_strtoupper(mb_substr($currentUser->name, 0, 1)) }}</div>
                        <div class="min-w-0 flex-1"><div class="truncate text-sm font-bold text-slate-800">{{ $currentUser->name }}</div><div class="text-xs text-slate-400">Minha conta</div></div>
                        <x-icon name="chevron-right" class="h-4 w-4 text-slate-400"/>
                    </a>
                @endif
                <form method="POST" action="{{ route('logout') }}" class="mt-1">@csrf<button class="sgml-sidebar-link w-full text-rose-700 hover:bg-rose-50 hover:text-rose-800"><x-icon name="logout"/>Sair</button></form>
            </div>
        </div>
    </aside>
    @endif

    <div class="min-w-0">
        <header class="sgml-topbar">
            <div class="flex h-[68px] items-center justify-between gap-3 px-4 lg:px-7">
                <div class="flex min-w-0 items-center gap-3">
                    @if($ministerOnlyNavigation)
                        <a href="{{ $homeRoute }}" class="flex min-w-0 items-center gap-2.5">
                            <img src="/brand/ourcloud-louvor.svg" alt="" class="h-9 w-9 shrink-0 rounded-xl shadow-sm">
                            <div class="min-w-0"><div class="truncate text-base font-black tracking-tight"><span class="text-indigo-700">OurCloud</span> Louvor</div><div class="hidden truncate text-xs text-slate-400 sm:block">Sua agenda ministerial</div></div>
                        </a>
                    @else
                        <button type="button" class="flex h-10 w-10 items-center justify-center rounded-xl text-slate-600 hover:bg-slate-100 lg:hidden" data-mobile-menu-open aria-label="Abrir menu"><x-icon name="menu"/></button>
                        <div class="lg:hidden"><div class="text-sm font-black"><span class="text-indigo-700">OurCloud</span> Louvor</div><div class="text-[11px] text-slate-400">Gestão do Ministério</div></div>
                        <div class="hidden lg:block"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ $safePageTitle }}</div><div class="text-sm font-semibold text-slate-700">{{ now()->translatedFormat('l, d \d\e F') }}</div></div>
                    @endif
                </div>
                <div class="flex items-center gap-1.5 sm:gap-2">
                    @if($canUseActionCenter)
                        <a href="{{ route('actions.index') }}" class="relative flex h-10 w-10 items-center justify-center rounded-xl text-slate-600 transition hover:bg-indigo-50 hover:text-indigo-700" title="Central de Ações"><x-icon name="bell"/></a>
                    @endif
                    @if($ministerOnlyNavigation)
                        <a href="{{ route('account.show') }}" class="hidden h-10 items-center gap-2 rounded-xl px-3 text-sm font-bold text-slate-600 hover:bg-slate-100 sm:flex"><x-icon name="user" class="h-4 w-4"/>Conta</a>
                        <form method="POST" action="{{ route('logout') }}">@csrf<button class="flex h-10 w-10 items-center justify-center rounded-xl text-rose-600 hover:bg-rose-50" title="Sair"><x-icon name="logout"/></button></form>
                    @else
                        @if(! $assistanceActive)<a href="{{ route('account.show') }}" class="hidden rounded-xl px-3 py-2 text-sm font-bold text-slate-600 hover:bg-slate-100 sm:inline-flex">{{ $currentUser->name }}</a>@endif
                    @endif
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-[1500px] px-4 py-5 sm:px-5 lg:px-7 lg:py-7">
            @if($assistedMinister)
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                    <div class="flex items-start gap-3"><x-icon name="info" class="mt-0.5 h-5 w-5 shrink-0"/><div><strong>Assistência ativa:</strong> interface e permissões de <strong>{{ $assistedMinister->display_name }}</strong> ({{ $currentUser->name }}). Executor real: <strong>{{ $realActor?->name }}</strong>.</div></div>
                    <form method="POST" action="{{ route('admin.assistance.stop') }}">@csrf<button class="sgml-btn-secondary">Encerrar assistência</button></form>
                </div>
            @endif

            @if($safeFlashStatus !== '')
                <div class="fixed right-4 top-20 z-[60] w-[calc(100%-2rem)] max-w-md transition duration-200 sm:right-6" data-toast role="status" aria-live="polite">
                    <div class="sgml-toast">
                        <div class="sgml-toast-icon"><x-icon name="check" class="h-5 w-5"/></div>
                        <div class="min-w-0 flex-1"><div class="font-black text-slate-900">Tudo certo</div><div class="mt-0.5 leading-relaxed">{{ $safeFlashStatus }}</div></div>
                        <button type="button" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700" data-toast-dismiss aria-label="Fechar"><x-icon name="x" class="h-4 w-4"/></button>
                    </div>
                </div>
            @endif

            @if($errors->any())
                <div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900" role="alert">
                    <div class="flex items-center gap-2 font-black"><x-icon name="alert" class="h-5 w-5"/>Verifique os campos informados</div>
                    <ul class="mt-2 list-disc space-y-1 pl-7">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            @yield('content')
        </main>
        <footer class="mx-auto max-w-[1500px] px-4 pb-8 text-center text-xs text-slate-400 lg:px-7">OurCloud Louvor · SGML v{{ config('app.version') }} · agenda, escala e memória musical do ministério</footer>
    </div>
</div>

@if(! $ministerOnlyNavigation)
<div class="fixed inset-0 z-[65] hidden bg-slate-950/40 p-3 backdrop-blur-sm lg:hidden" data-mobile-menu>
    <div class="ml-auto flex h-full w-full max-w-sm flex-col overflow-y-auto rounded-3xl bg-white p-4 shadow-2xl">
        <div class="mb-4 flex items-center justify-between gap-3 border-b border-slate-100 pb-4">
            <div class="flex items-center gap-3"><img src="/brand/ourcloud-louvor.svg" alt="" class="h-10 w-10 rounded-xl"><div><div class="font-black"><span class="text-indigo-700">OurCloud</span> Louvor</div><div class="text-xs text-slate-400">Menu</div></div></div>
            <button type="button" class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-600" data-mobile-menu-close><x-icon name="x"/></button>
        </div>
        <nav class="space-y-1 text-sm">
            @if($currentUser->hasPermission('reports.dashboard'))<a class="sgml-mobile-link" href="{{ route('dashboard') }}"><x-icon name="home"/>Visão geral</a>@endif
            @if($canSeePersonalAgenda)<a class="sgml-mobile-link" href="{{ route('my.agenda') }}"><x-icon name="calendar"/>Minha Agenda</a>@endif
            @if($currentUser->hasPermission('calendar.view'))<a class="sgml-mobile-link" href="{{ route('calendar.index') }}"><x-icon name="calendar"/>Agenda do Ministério</a>@endif
            @if($currentUser->hasPermission('schedule.view'))<a class="sgml-mobile-link" href="{{ route('schedule.index') }}"><x-icon name="clipboard"/>Escalas</a>@endif
            @if($canManageServices)<a class="sgml-mobile-link" href="{{ route('services.index') }}"><x-icon name="book"/>Cultos</a>@endif
            @if($canSeeSongs)<a class="sgml-mobile-link" href="{{ $currentUser->hasPermission('songs.view') ? route('songs.index') : route('songs.history') }}"><x-icon name="music"/>Biblioteca de Louvores</a>@endif
            <a class="sgml-mobile-link" href="{{ route('programmed-songs.index') }}"><x-icon name="music"/>Louvores programados</a>
            @if($currentUser->hasPermission('rehearsals.view'))<a class="sgml-mobile-link" href="{{ route('rehearsals.index') }}"><x-icon name="mic"/>Ensaios</a>@endif
            @if($currentUser->hasPermission('schedule.swap'))<a class="sgml-mobile-link" href="{{ route('swaps.index') }}"><x-icon name="repeat"/><span class="flex-1">Trocas</span>@if($swapAlertCount > 0)<span class="rounded-full bg-rose-600 px-2 py-0.5 text-[10px] font-black text-white">{{ $swapAlertCount }}</span>@endif</a>@endif
            @if($canSeeSpecialEvents)<a class="sgml-mobile-link" href="{{ route('special-events.index') }}"><x-icon name="sparkles"/>Eventos especiais</a>@endif
            @if($canSeeBirthdays)<a class="sgml-mobile-link" href="{{ route('birthdays.index') }}"><x-icon name="cake"/>Aniversariantes</a>@endif
            @if($currentUser->hasPermission('users.view'))<a class="sgml-mobile-link" href="{{ route('people.index') }}"><x-icon name="users"/>Pessoas</a>@endif
            @if($currentUser->hasPermission('reports.dashboard'))<a class="sgml-mobile-link" href="{{ route('reports.index') }}"><x-icon name="chart"/>Relatórios</a>@endif
            @if($canSeeAdministration)<a class="sgml-mobile-link" href="{{ route('admin.index') }}"><x-icon name="settings"/>Administração</a>@endif
        </nav>
        <div class="mt-auto border-t border-slate-100 pt-4">
            @if(! $assistanceActive)<a class="sgml-mobile-link" href="{{ route('account.show') }}"><x-icon name="user"/>Minha conta</a>@endif
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="sgml-mobile-link w-full text-rose-700"><x-icon name="logout"/>Sair</button></form>
        </div>
    </div>
</div>
@endif

<nav class="sgml-mobile-bottom {{ $ministerOnlyNavigation ? 'grid-cols-4' : 'grid-cols-5' }}" aria-label="Navegação rápida">
    @if($ministerOnlyNavigation)
        <a class="{{ request()->routeIs('my.agenda','availability.*','my.agenda.*') ? 'active' : '' }}" href="{{ route('my.agenda') }}"><x-icon name="calendar"/><span>Agenda</span></a>
        <a class="{{ request()->routeIs('programmed-songs.*') ? 'active' : '' }}" href="{{ route('programmed-songs.index') }}"><x-icon name="music"/><span>Louvores</span></a>
        <a class="{{ request()->routeIs('actions.*') ? 'active' : '' }}" href="{{ route('actions.index') }}"><x-icon name="bell"/><span>Ações</span></a>
        <a class="{{ request()->routeIs('account.*') ? 'active' : '' }}" href="{{ route('account.show') }}"><x-icon name="user"/><span>Conta</span></a>
    @else
        <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ $currentUser->hasPermission('reports.dashboard') ? route('dashboard') : $homeRoute }}"><x-icon name="home"/><span>Início</span></a>
        @if($currentUser->hasPermission('calendar.view'))<a class="{{ request()->routeIs('calendar.*') ? 'active' : '' }}" href="{{ route('calendar.index') }}"><x-icon name="calendar"/><span>Agenda</span></a>@elseif($canSeePersonalAgenda)<a class="{{ request()->routeIs('my.agenda') ? 'active' : '' }}" href="{{ route('my.agenda') }}"><x-icon name="calendar"/><span>Agenda</span></a>@else<a href="{{ route('programmed-songs.index') }}"><x-icon name="music"/><span>Louvores</span></a>@endif
        @if($currentUser->hasPermission('schedule.view'))<a class="{{ request()->routeIs('schedule.*') ? 'active' : '' }}" href="{{ route('schedule.index') }}"><x-icon name="clipboard"/><span>Escalas</span></a>@else<a href="{{ route('programmed-songs.index') }}"><x-icon name="music"/><span>Louvores</span></a>@endif
        <a class="{{ request()->routeIs('actions.*') ? 'active' : '' }}" href="{{ route('actions.index') }}"><x-icon name="bell"/><span>Ações</span></a>
        <button type="button" class="flex min-h-12 flex-col items-center justify-center gap-0.5 rounded-xl px-1 text-[10px] font-bold text-slate-500" data-mobile-menu-open><x-icon name="menu" class="h-5 w-5"/><span>Menu</span></button>
    @endif
</nav>

<div class="sgml-page-loading" data-page-loader aria-hidden="true">
    <div class="sgml-loading-bar"></div>
    <div class="sgml-loading-skeleton">
        <div class="sgml-skeleton-line w-1/3"></div><div class="sgml-skeleton-line h-28 w-full"></div><div class="sgml-skeleton-line w-4/5"></div><div class="sgml-skeleton-line w-2/3"></div>
    </div>
</div>

<div class="sgml-dialog-backdrop hidden" data-confirm-dialog role="dialog" aria-modal="true" aria-labelledby="sgml-confirm-title">
    <div class="sgml-dialog">
        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-700"><x-icon name="alert"/></div>
        <h2 id="sgml-confirm-title" class="text-xl font-black text-slate-950" data-confirm-dialog-title>Confirmar ação</h2>
        <p class="mt-2 text-sm leading-relaxed text-slate-600" data-confirm-dialog-message>Deseja continuar?</p>
        <div class="mt-4 hidden" data-confirm-dialog-reason-wrap>
            <label class="sgml-label" for="sgml-confirm-reason" data-confirm-dialog-reason-label>Motivo</label>
            <textarea id="sgml-confirm-reason" rows="3" class="sgml-input" data-confirm-dialog-reason placeholder="Descreva brevemente o motivo"></textarea>
            <p class="sgml-help" data-confirm-dialog-reason-help>Informe pelo menos 3 caracteres.</p>
        </div>
        <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"><button type="button" class="sgml-btn-secondary" data-confirm-dialog-cancel>Cancelar</button><button type="button" class="sgml-btn-primary" data-confirm-dialog-confirm>Confirmar</button></div>
    </div>
</div>

@livewireScriptConfig
</body>
</html>
