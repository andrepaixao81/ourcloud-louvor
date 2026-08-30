@extends('layouts.app')
@section('content')
<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <a href="{{ route('songs.index') }}" class="text-sm font-semibold text-indigo-600">← Biblioteca de louvores</a>
        <h1 class="mt-2 text-2xl font-black">Importar louvores</h1>
        <p class="text-sm text-slate-500">Use o catálogo fictício de demonstração ou importe seu próprio arquivo CSV. A importação é idempotente e tenta enriquecer registros existentes sem duplicá-los.</p>
    </div>
</div>

@if($errors->any())
    <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
        <ul class="list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div class="grid gap-5 lg:grid-cols-2">
    <div class="sgml-card p-5">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold">Catálogo de exemplo</h2>
                <p class="mt-1 text-sm text-slate-500">Três registros fictícios para demonstrar a importação sem distribuir dados de nenhuma igreja ou repertório real.</p>
            </div>
            <span class="sgml-badge bg-indigo-50 text-indigo-700">3 exemplos</span>
        </div>
        <div class="mt-4 rounded-xl border border-amber-100 bg-amber-50 p-3 text-sm text-amber-800">
            Os itens são fictícios e servem apenas para teste. A importação não cria ministrações no histórico.
        </div>
        <form method="POST" action="{{ route('songs.import.catalog') }}" class="mt-5" data-confirm-message="Importar o catálogo fictício de exemplo? Registros existentes serão preservados e apenas enriquecidos quando possível.">
            @csrf
            <button class="sgml-btn-primary">Importar catálogo de exemplo</button>
        </form>
    </div>

    <div class="sgml-card p-5">
        <h2 class="text-lg font-bold">Importar arquivo CSV</h2>
        <p class="mt-1 text-sm text-slate-500">Para seu repertório real. Aceita arquivo de até 2 MB com separador ponto e vírgula, vírgula ou TAB.</p>
        <div class="mt-4 text-sm text-slate-600">
            <strong>Colunas reconhecidas:</strong> título, artista, tom original, categoria, título alternativo e observação. Use o modelo CSV disponível nesta tela.
        </div>
        <a href="{{ route('songs.import.template') }}" class="mt-3 inline-block text-sm font-semibold text-indigo-600">Baixar modelo CSV</a>

        <form method="POST" action="{{ route('songs.import.csv') }}" enctype="multipart/form-data" class="mt-5 space-y-4">
            @csrf
            <div>
                <label class="sgml-label">Arquivo CSV</label>
                <input class="sgml-input" type="file" name="file" accept=".csv,.txt,text/csv,text/plain" required>
            </div>
            <button class="sgml-btn-primary">Importar CSV</button>
        </form>
    </div>
</div>

<div class="mt-5 sgml-card p-5">
    <h2 class="font-bold">Como o SGML trata duplicidades</h2>
    <div class="mt-2 grid gap-3 text-sm text-slate-600 md:grid-cols-3">
        <div><strong>1. Normaliza o título</strong><br>Maiúsculas/minúsculas e acentos não criam outro cadastro por engano.</div>
        <div><strong>2. Preserva o que já existe</strong><br>Se encontrar o louvor, completa categoria, artista ou tom somente quando o campo estiver vazio.</div>
        <div><strong>3. Não inventa histórico</strong><br>Importar uma música para a biblioteca não aumenta o contador de ministrações.</div>
    </div>
</div>
@endsection
