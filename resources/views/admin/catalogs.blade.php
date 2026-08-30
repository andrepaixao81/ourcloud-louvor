@extends('layouts.app')
@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-black">Catálogos administrativos</h1>
    <p class="text-sm text-slate-500">Funções ministeriais e categorias de louvores podem ser cadastradas, editadas, desativadas, excluídas logicamente e restauradas.</p>
</div>

<div class="grid gap-6 xl:grid-cols-2">
    <div class="space-y-4">
        <div class="sgml-card p-5">
            <h2 class="font-bold">Nova função ministerial</h2>
            <form method="POST" action="{{ route('admin.ministry-roles.store') }}" class="mt-4 space-y-3">
                @csrf
                <input class="sgml-input" name="name" placeholder="Ex.: Saxofone" required>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_minister_role" value="1">
                    Esta função identifica pessoa elegível como ministro para a escala
                </label>
                <button class="sgml-btn-primary">Cadastrar função</button>
            </form>
        </div>

        @foreach($ministryRoles as $role)
            <div class="sgml-card p-5 {{ $role->trashed() ? 'opacity-70' : '' }}">
                @if($role->trashed())
                    <div class="text-xs font-bold uppercase tracking-wide text-amber-700">Excluída logicamente</div>
                    <div class="mt-1 font-bold">{{ $role->name }}</div>
                    <form method="POST" action="{{ route('admin.ministry-roles.restore', $role->id) }}" class="mt-3">
                        @csrf
                        <button class="sgml-btn-secondary">Restaurar função</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.ministry-roles.update', $role) }}" class="space-y-3">
                        @csrf
                        @method('PATCH')
                        <input class="sgml-input" name="name" value="{{ $role->name }}" required>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="is_minister_role" value="1" @checked($role->is_minister_role)>
                            Elegível como ministro da escala
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="active" value="1" @checked($role->active)>
                            Ativa para novos cadastros
                        </label>
                        <button class="sgml-btn-secondary">Salvar alterações</button>
                    </form>
                    <form method="POST" action="{{ route('admin.ministry-roles.destroy', $role) }}" class="mt-3" data-confirm-message="Excluir logicamente esta função ministerial?">
                        @csrf
                        @method('DELETE')
                        <button class="sgml-btn-danger">Excluir função</button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>

    <div class="space-y-4">
        <div class="sgml-card p-5">
            <h2 class="font-bold">Nova categoria de louvor</h2>
            <form method="POST" action="{{ route('admin.song-categories.store') }}" class="mt-4 space-y-3">
                @csrf
                <input class="sgml-input" name="name" placeholder="Nome da categoria" required>
                <textarea class="sgml-input" name="description" placeholder="Descrição opcional"></textarea>
                <button class="sgml-btn-primary">Cadastrar categoria</button>
            </form>
        </div>

        @foreach($songCategories as $category)
            <div class="sgml-card p-5 {{ $category->trashed() ? 'opacity-70' : '' }}">
                @if($category->trashed())
                    <div class="text-xs font-bold uppercase tracking-wide text-amber-700">Excluída logicamente</div>
                    <div class="mt-1 font-bold">{{ $category->name }}</div>
                    <form method="POST" action="{{ route('admin.song-categories.restore', $category->id) }}" class="mt-3">
                        @csrf
                        <button class="sgml-btn-secondary">Restaurar categoria</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.song-categories.update', $category) }}" class="space-y-3">
                        @csrf
                        @method('PATCH')
                        <input class="sgml-input" name="name" value="{{ $category->name }}" required>
                        <textarea class="sgml-input" name="description" placeholder="Descrição opcional">{{ $category->description }}</textarea>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="active" value="1" @checked($category->active)>
                            Ativa para filtros e novos louvores
                        </label>
                        <button class="sgml-btn-secondary">Salvar alterações</button>
                    </form>
                    <form method="POST" action="{{ route('admin.song-categories.destroy', $category) }}" class="mt-3" data-confirm-message="Excluir logicamente esta categoria?">
                        @csrf
                        @method('DELETE')
                        <button class="sgml-btn-danger">Excluir categoria</button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>
</div>
@endsection
