@extends('layouts.app')
@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-black">Administração</h1>
    <p class="text-sm text-slate-500">Configurações da organização, acessos, catálogos, integrações e auditoria.</p>
</div>


@if($canMinisterAssistance)
<div class="mb-6 sgml-card border border-indigo-100 p-5">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-lg font-black">Assistência ao ministro</h2>
                <span class="sgml-badge bg-indigo-50 text-indigo-700">Administrador / Líder do Louvor</span>
            </div>
            <p class="mt-1 text-sm text-slate-500">Abre o SGML com a mesma visão, menus e permissões da conta do ministro selecionado. Você poderá executar as operações que aquela conta realmente possui, enquanto a auditoria registra seu usuário real como executor.</p>
        </div>
        @if($assistedPerson)
            <form method="POST" action="{{ route('admin.assistance.stop') }}">
                @csrf
                <button class="sgml-btn-danger">Encerrar assistência</button>
            </form>
        @endif
    </div>

    @if($assistedPerson)
        <div class="mt-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-900">
            Você está atuando como a conta de <strong>{{ $assistedPerson->display_name }}</strong>. A interface exibida é a mesma do usuário assistido e ações como confirmações, trocas e repertório são registradas no contexto dele.
        </div>
    @else
        <form method="POST" action="{{ route('admin.assistance.start') }}" class="mt-4 grid gap-3 lg:grid-cols-[minmax(240px,1fr)_minmax(280px,2fr)_auto]">
            @csrf
            <select class="sgml-input" name="person_id" required>
                <option value="">Selecione o ministro</option>
                @foreach($ministers as $minister)
                    <option value="{{ $minister->id }}">{{ $minister->display_name }} · {{ $minister->user?->name }}</option>
                @endforeach
            </select>
            <input class="sgml-input" name="reason" required minlength="3" maxlength="500" placeholder="Motivo da assistência (ex.: ajudar em uma troca ou repertório)">
            <button class="sgml-btn-primary">Atuar como usuário</button>
        </form>
    @endif
</div>
@endif

<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    @perm('administration.settings')
        <a href="{{ route('congregations.index') }}" class="sgml-card block p-5 transition hover:border-indigo-200 hover:shadow-md">
            <div class="text-lg font-bold">Congregações e locais</div>
            <p class="mt-1 text-sm text-slate-500">Cadastre congregações, templos e outros locais utilizados pela organização.</p>
        </a>
        <a href="{{ route('service-types.index') }}" class="sgml-card block p-5 transition hover:border-indigo-200 hover:shadow-md">
            <div class="text-lg font-bold">Tipos de culto</div>
            <p class="mt-1 text-sm text-slate-500">Configure os tipos de culto e quais exigem autorização ministerial específica.</p>
        </a>
        <a href="{{ route('admin.minister-eligibility') }}" class="sgml-card block p-5 transition hover:border-indigo-200 hover:shadow-md">
            <div class="flex items-center justify-between gap-2"><span class="text-lg font-bold">Cultos em que pode ministrar</span><span class="sgml-badge bg-indigo-50 text-indigo-700">por grupo</span></div>
            <p class="mt-1 text-sm text-slate-500">Abra um tipo de culto, veja todos os ministros habilitados e adicione ou remova pessoas em uma única tela.</p>
        </a>
        <a href="{{ route('admin.catalogs') }}" class="sgml-card block p-5 transition hover:border-indigo-200 hover:shadow-md">
            <div class="text-lg font-bold">Catálogos ministeriais</div>
            <p class="mt-1 text-sm text-slate-500">Funções ministeriais e categorias de louvores.</p>
        </a>
        <a href="{{ route('services.recurring') }}" class="sgml-card block p-5 transition hover:border-indigo-200 hover:shadow-md">
            <div class="text-lg font-bold">Regras da agenda</div>
            <p class="mt-1 text-sm text-slate-500">Revise, edite, desative ou exclua as regras que geram cultos recorrentes.</p>
        </a>
    @endperm

    @if(auth()->user()->isAdministrator())
        <a href="{{ route('admin.mail.index') }}" class="sgml-card block p-5 transition hover:border-indigo-200 hover:shadow-md">
            <div class="text-lg font-bold">SMTP e e-mails</div>
            <p class="mt-1 text-sm text-slate-500">Área exclusiva do Administrador para servidor de e-mail, senhas e recuperações.</p>
        </a>
        <a href="{{ route('admin.users') }}" class="sgml-card block p-5 transition hover:border-indigo-200 hover:shadow-md">
            <div class="flex items-center justify-between gap-2"><span class="text-lg font-bold">Usuários e acessos</span><span class="sgml-badge bg-indigo-50 text-indigo-700">RBAC + ACL</span></div>
            <p class="mt-1 text-sm text-slate-500">Área exclusiva do Administrador para login, papéis e permissões individuais.</p>
        </a>
        <a href="{{ route('admin.groups') }}" class="sgml-card block p-5 transition hover:border-indigo-200 hover:shadow-md">
            <div class="flex items-center justify-between gap-2"><span class="text-lg font-bold">Grupos de acesso</span><span class="sgml-badge bg-violet-50 text-violet-700">RBAC</span></div>
            <p class="mt-1 text-sm text-slate-500">Área exclusiva do Administrador para composição dos grupos de acesso.</p>
        </a>
        <a href="{{ route('audit.index') }}" class="sgml-card block p-5 transition hover:border-indigo-200 hover:shadow-md">
            <div class="text-lg font-bold">Auditoria</div>
            <p class="mt-1 text-sm text-slate-500">Área exclusiva do Administrador para alterações, responsáveis e eventos relevantes.</p>
        </a>
        <a href="{{ route('admin.backups.index') }}" class="sgml-card block p-5 transition hover:border-indigo-200 hover:shadow-md">
            <div class="flex items-center justify-between gap-2"><span class="text-lg font-bold">Backup e restauração</span><span class="sgml-badge bg-emerald-50 text-emerald-700">PostgreSQL</span></div>
            <p class="mt-1 text-sm text-slate-500">Área exclusiva do Administrador para gerar, importar, baixar e restaurar backups portáteis do banco.</p>
        </a>
        <a href="{{ route('admin.operational-cleanup.index') }}" class="sgml-card block p-5 transition hover:border-rose-200 hover:shadow-md">
            <div class="flex items-center justify-between gap-2"><span class="text-lg font-bold">Limpeza de dados de teste</span><span class="sgml-badge bg-rose-50 text-rose-700">Protegida</span></div>
            <p class="mt-1 text-sm text-slate-500">Remove escalas, trocas e resíduos operacionais de homologação, preservando cadastros, biblioteca, ensaios e regras dos cultos.</p>
        </a>
        <a href="{{ route('admin.whatsapp.index') }}" class="sgml-card block p-5 transition hover:border-indigo-200 hover:shadow-md">
            <div class="flex items-center justify-between gap-2"><span class="text-lg font-bold">Avisos pelo WhatsApp</span><span class="sgml-badge bg-emerald-50 text-emerald-700">Opcional</span></div>
            <p class="mt-1 text-sm text-slate-500">Configure avisos automáticos de escala, repertório e eventos para a equipe do louvor.</p>
        </a>
    @endif

    @perm('administration.integrations')
        <a href="{{ route('integrations.index') }}" class="sgml-card block p-5 transition hover:border-indigo-200 hover:shadow-md">
            <div class="text-lg font-bold">Integrações</div>
            <p class="mt-1 text-sm text-slate-500">Integrações opcionais, mantendo o SGML como fonte oficial dos dados.</p>
        </a>
    @endperm
</div>
@endsection
