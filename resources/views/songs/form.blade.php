@extends('layouts.app')
@section('content')
@php($referenceVersion = $referenceVersion ?? null)
<div class="mb-6">
    <a href="{{ $song->exists ? route('songs.show',$song) : route('songs.index') }}" class="text-sm font-semibold text-indigo-600">← Voltar</a>
    <h1 class="mt-2 text-2xl font-black">{{ $song->exists ? 'Editar louvor' : 'Novo louvor' }}</h1>
</div>

@if(session('duplicates'))
<div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-900">
    <div class="font-bold">Possíveis duplicidades encontradas</div>
    <ul class="mt-2 list-disc pl-5 text-sm">
        @foreach(session('duplicates') as $d)
            <li>{{ $d->title }} @if($d->artist_reference)- {{ $d->artist_reference }}@endif</li>
        @endforeach
    </ul>
    <p class="mt-2 text-sm">Revise a lista. Para prosseguir mesmo assim, marque a confirmação abaixo.</p>
</div>
@endif

<form method="POST" action="{{ $song->exists ? route('songs.update',$song) : route('songs.store') }}" class="space-y-5">
    @csrf
    @if($song->exists) @method('PATCH') @endif

    <section class="sgml-card grid gap-4 p-6 md:grid-cols-2">
        <div>
            <label class="sgml-label">Título</label>
            <input class="sgml-input" name="title" value="{{ old('title',$song->title) }}" required>
        </div>
        <div>
            <label class="sgml-label">Título alternativo</label>
            <input class="sgml-input" name="alternative_title" value="{{ old('alternative_title',$song->alternative_title) }}">
        </div>
        <div>
            <label class="sgml-label">Artista/referência</label>
            <input class="sgml-input" name="artist_reference" value="{{ old('artist_reference',$song->artist_reference) }}">
        </div>
        <div>
            <label class="sgml-label">Categoria</label>
            <select class="sgml-input" name="category_id">
                <option value="">Sem categoria</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected(old('category_id',$song->category_id)===$category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="md:col-span-2">
            <label class="sgml-label">Observações</label>
            <textarea class="sgml-input" name="notes" rows="3">{{ old('notes',$song->notes) }}</textarea>
        </div>
    </section>

    <section class="sgml-card p-6">
        <div class="mb-4">
            <h2 class="text-lg font-black">Referência principal</h2>
            <p class="mt-1 text-sm text-slate-500">O link do YouTube e o tom desta referência são usados em Ensaios e como padrão ao montar repertórios. Você pode cadastrar ou corrigir esses dados a qualquer momento.</p>
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="sgml-label">Nome da versão <span class="font-normal text-slate-400">(opcional)</span></label>
                <input class="sgml-input" name="version_name" value="{{ old('version_name',$referenceVersion?->name) }}" placeholder="Ex.: versão congregacional">
            </div>
            <div>
                <label class="sgml-label">Link de referência no YouTube</label>
                <input class="sgml-input" type="url" name="youtube_url" value="{{ old('youtube_url',$referenceVersion?->youtube_url) }}" placeholder="https://www.youtube.com/watch?v=...">
                <p class="mt-1 text-xs text-slate-500">Este é o link que aparecerá para a equipe em Ensaios.</p>
            </div>
            <div>
                <label class="sgml-label">Tom principal</label>
                <input class="sgml-input" name="original_key" value="{{ old('original_key',$referenceVersion?->original_key) }}" placeholder="Ex.: G">
            </div>
            <div>
                <label class="sgml-label">BPM <span class="font-normal text-slate-400">(opcional)</span></label>
                <input class="sgml-input" type="number" min="30" max="300" name="bpm" value="{{ old('bpm',$referenceVersion?->bpm) }}">
            </div>
            @if($referenceVersion?->youtube_url)
                <div class="md:col-span-2 rounded-xl border border-indigo-100 bg-indigo-50 p-4 text-sm text-indigo-900">
                    <strong>Referência atual:</strong>
                    <a class="ml-1 font-bold underline" href="{{ $referenceVersion->youtube_url }}" target="_blank" rel="noopener noreferrer">▶ Abrir no YouTube</a>
                </div>
            @endif
        </div>
    </section>

    @if(session('duplicates'))
        <label class="flex items-center gap-2 text-sm font-semibold text-amber-900">
            <input type="checkbox" name="confirm_duplicate" value="1"> Confirmo que este é um cadastro distinto e desejo prosseguir.
        </label>
    @endif

    <div>
        <button class="sgml-btn-primary">{{ $song->exists ? 'Salvar alterações' : 'Salvar louvor' }}</button>
    </div>
</form>
@endsection
