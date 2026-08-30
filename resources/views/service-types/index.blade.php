@extends('layouts.app')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-black">Tipos de culto</h1>
    <p class="text-sm text-slate-500">Tipos configuráveis e regras especiais de autorização.</p>
</div>

@if($errors->has('delete') || $errors->has('active'))
    <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
        {{ $errors->first('delete') ?: $errors->first('active') }}
    </div>
@endif

<div class="grid gap-6 lg:grid-cols-2">
    <form method="POST" action="{{ route('service-types.store') }}" class="sgml-card space-y-4 p-5">
        @csrf
        <h2 class="font-bold">Novo tipo</h2>
        <div><label class="sgml-label">Nome</label><input class="sgml-input" name="name" required></div>
        <div><label class="sgml-label">Descrição</label><textarea class="sgml-input" name="description"></textarea></div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="requires_authorization" value="1"> Exigir autorização específica do ministro</label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="manual_minister_assignment" value="1"> Ministro definido manualmente (não gerar automaticamente)</label>
        <button class="sgml-btn-primary">Cadastrar tipo</button>
    </form>

    <div class="space-y-3">
        @forelse($types as $type)
            <div class="sgml-card p-5 {{ $type->trashed() ? 'opacity-70' : '' }}">
                @if($type->trashed())
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-semibold">{{ $type->name }}</div>
                            <div class="mt-1 text-xs text-slate-500">Excluído · {{ $type->requires_authorization ? 'Exige autorização específica' : 'Sem autorização especial' }}</div>
                        </div>
                        <form method="POST" action="{{ route('service-types.restore', $type->id) }}" data-confirm-message="Restaurar este tipo de culto?">
                            @csrf
                            <button class="sgml-btn-secondary">Restaurar</button>
                        </form>
                    </div>
                @else
                    <form method="POST" action="{{ route('service-types.update', $type) }}" class="space-y-3">
                        @csrf
                        @method('PATCH')
                        <div><label class="sgml-label">Nome</label><input class="sgml-input" name="name" value="{{ $type->name }}" required></div>
                        <div><label class="sgml-label">Descrição</label><textarea class="sgml-input" name="description">{{ $type->description }}</textarea></div>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="requires_authorization" value="1" @checked($type->requires_authorization)> Exigir autorização específica do ministro</label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="manual_minister_assignment" value="1" @checked($type->manual_minister_assignment)> Ministro definido manualmente (não gerar automaticamente)</label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="active" value="1" @checked($type->active)> Ativo para novos cultos</label>
                        @php($authorizedMinisters = $type->ministerPermissions->filter(fn ($permission) => $permission->person)->pluck('person.display_name')->sort()->values())
                        <div class="rounded-xl bg-slate-50 p-3 text-xs text-slate-600">
                            <div class="font-semibold text-slate-700">Ministros habilitados: {{ $authorizedMinisters->count() }}</div>
                            <div class="mt-1">{{ $authorizedMinisters->isEmpty() ? 'Nenhum ministro habilitado.' : $authorizedMinisters->take(5)->join(', ').($authorizedMinisters->count() > 5 ? ' +'.($authorizedMinisters->count() - 5) : '') }}</div>
                            <div class="mt-1 text-slate-400">A habilitação é administrada no cadastro da pessoa.</div>
                        </div>
                        <button class="sgml-btn-secondary">Salvar alterações</button>
                    </form>
                    <form method="POST" action="{{ route('service-types.destroy', $type) }}" class="mt-3" data-confirm-title="Excluir tipo de culto?" data-confirm-message="O tipo será arquivado e o histórico será preservado." data-confirm-danger="1" data-confirm-label="Excluir tipo" data-confirm-reason-label="Motivo da exclusão" data-confirm-reason-min="3">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="reason" value="">
                        <button class="sgml-btn-danger">Excluir tipo</button>
                    </form>
                @endif
            </div>
        @empty
            <div class="sgml-card p-10 text-center text-slate-500">Nenhum tipo de culto cadastrado.</div>
        @endforelse
    </div>
</div>
@endsection
