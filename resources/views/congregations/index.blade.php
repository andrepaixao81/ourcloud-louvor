@extends('layouts.app')
@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-black">Estrutura organizacional</h1>
    <p class="text-sm text-slate-500">Cadastre, edite, desative, exclua logicamente e restaure congregações e locais usados pelos cultos.</p>
</div>

<div class="grid gap-6 lg:grid-cols-2">
    <div class="space-y-4">
        <div class="sgml-card p-5">
            <h2 class="font-bold">Nova congregação</h2>
            <form method="POST" action="{{ route('congregations.store') }}" class="mt-4 grid gap-3">
                @csrf
                <input class="sgml-input" name="name" placeholder="Nome da congregação" required>
                <input class="sgml-input" name="code" placeholder="Código opcional">
                <textarea class="sgml-input" name="description" placeholder="Descrição"></textarea>
                <button class="sgml-btn-primary justify-self-start">Cadastrar</button>
            </form>
        </div>

        @foreach($congregations as $c)
            <div class="sgml-card p-5 {{ $c->trashed() ? 'opacity-70' : '' }}">
                @if($c->trashed())
                    <div class="mb-3 text-xs font-bold uppercase tracking-wide text-amber-700">Excluída logicamente</div>
                    <div class="font-bold">{{ $c->name }}</div>
                    <div class="mt-3"><form method="POST" action="{{ route('congregations.restore',$c->id) }}">@csrf<button class="sgml-btn-secondary">Restaurar</button></form></div>
                @else
                    <form method="POST" action="{{ route('congregations.update',$c) }}" class="grid gap-3">
                        @csrf @method('PATCH')
                        <input class="sgml-input" name="name" value="{{ $c->name }}" required>
                        <input class="sgml-input" name="code" value="{{ $c->code }}" placeholder="Código opcional">
                        <textarea class="sgml-input" name="description" placeholder="Descrição">{{ $c->description }}</textarea>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="active" value="1" @checked($c->active)> Ativa para novos cadastros</label>
                        <div class="flex flex-wrap gap-2"><button class="sgml-btn-secondary">Salvar alterações</button></div>
                    </form>
                    <form method="POST" action="{{ route('congregations.destroy',$c) }}" class="mt-3" data-confirm-message="Excluir logicamente esta congregação? O histórico será preservado.">
                        @csrf @method('DELETE')
                        <button class="sgml-btn-danger">Excluir congregação</button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>

    <div class="space-y-4">
        <div class="sgml-card p-5">
            <h2 class="font-bold">Novo local</h2>
            <form method="POST" action="{{ route('locations.store') }}" class="mt-4 grid gap-3">
                @csrf
                <input class="sgml-input" name="name" placeholder="Nome do local" required>
                <select class="sgml-input" name="congregation_id"><option value="">Sem congregação</option>@foreach($congregations->whereNull('deleted_at')->where('active',true) as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
                <input class="sgml-input" name="address" placeholder="Endereço opcional">
                <button class="sgml-btn-primary justify-self-start">Cadastrar</button>
            </form>
        </div>

        @foreach($locations as $l)
            <div class="sgml-card p-5 {{ $l->trashed() ? 'opacity-70' : '' }}">
                @if($l->trashed())
                    <div class="mb-3 text-xs font-bold uppercase tracking-wide text-amber-700">Excluído logicamente</div>
                    <div class="font-bold">{{ $l->name }}</div>
                    <div class="mt-3"><form method="POST" action="{{ route('locations.restore',$l->id) }}">@csrf<button class="sgml-btn-secondary">Restaurar</button></form></div>
                @else
                    <form method="POST" action="{{ route('locations.update',$l) }}" class="grid gap-3">
                        @csrf @method('PATCH')
                        <input class="sgml-input" name="name" value="{{ $l->name }}" required>
                        <select class="sgml-input" name="congregation_id"><option value="">Sem congregação</option>@foreach($congregations->whereNull('deleted_at') as $c)<option value="{{ $c->id }}" @selected($l->congregation_id === $c->id)>{{ $c->name }}</option>@endforeach</select>
                        <input class="sgml-input" name="address" value="{{ $l->address }}" placeholder="Endereço opcional">
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="active" value="1" @checked($l->active)> Ativo para novos cultos</label>
                        <button class="sgml-btn-secondary justify-self-start">Salvar alterações</button>
                    </form>
                    <form method="POST" action="{{ route('locations.destroy',$l) }}" class="mt-3" data-confirm-message="Excluir logicamente este local? O histórico será preservado.">
                        @csrf @method('DELETE')
                        <button class="sgml-btn-danger">Excluir local</button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>
</div>

<div class="mt-6 flex flex-wrap gap-3">
    <a href="{{ route('service-types.index') }}" class="sgml-btn-secondary">Tipos de culto</a>
    <a href="{{ route('admin.catalogs') }}" class="sgml-btn-secondary">Funções e categorias</a>
    <a href="{{ route('services.recurring') }}" class="sgml-btn-secondary">Regras da agenda</a>
    @if(auth()->user()?->isAdministrator())<a href="{{ route('admin.users') }}" class="sgml-btn-secondary">Usuários e papéis</a>@endif
    @if(auth()->user()?->isAdministrator())<a href="{{ route('admin.groups') }}" class="sgml-btn-secondary">Grupos de acesso</a>@endif
    @perm('administration.integrations')<a href="{{ route('integrations.index') }}" class="sgml-btn-secondary">Google Agenda</a>@endperm
    @if(auth()->user()?->isAdministrator())<a href="{{ route('audit.index') }}" class="sgml-btn-secondary">Auditoria</a>@endif
</div>
@endsection
