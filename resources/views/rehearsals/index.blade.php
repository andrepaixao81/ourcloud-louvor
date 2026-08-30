@extends('layouts.app')
@section('content')
<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h1 class="text-2xl font-black">Ensaios</h1>
        <p class="text-sm text-slate-500">Acompanhe os tons dos próximos cultos durante o ensaio e mantenha também a biblioteca de louvores que o ministério está aprendendo ou aprimorando.</p>
    </div>
    <div class="flex flex-wrap gap-2 text-sm">
        @foreach(['practicing'=>'Em ensaio','ready'=>'Prontos','removed'=>'Removidos','all'=>'Todos'] as $value=>$label)
            <a class="{{ $status===$value ? 'sgml-btn-primary' : 'sgml-btn-secondary' }}" href="{{ route('rehearsals.index',['status'=>$value]) }}">{{ $label }}</a>
        @endforeach
    </div>
</div>

@if(session('status'))
    <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('status') }}</div>
@endif

<section class="mb-8">
    <div class="mb-4">
        <h2 class="text-xl font-black text-slate-950">Tons do ensaio dos próximos cultos</h2>
        <p class="mt-1 text-sm text-slate-500">Todos os músicos podem consultar quem ministrará cada louvor e em qual tom. O ministro daquele louvor pode ajustar o tom durante o ensaio; a alteração entra no repertório e fica lembrada para as próximas vezes.</p>
    </div>

    <div class="space-y-4">
        @forelse($upcomingServices as $service)
            @php
                $repertoire = $service->repertoire;
                $primaryName = $service->assignment?->minister?->display_name;
                $additionalNames = $service->additionalMinisters->where('status','published')->pluck('minister.display_name')->filter();
            @endphp
            <article class="sgml-card overflow-hidden">
                <div class="border-b border-slate-200 bg-slate-50 px-5 py-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="font-black text-slate-950">{{ $service->name }}</h3>
                            <div class="mt-1 text-sm text-slate-600">{{ $service->starts_at->format('d/m/Y') }} · {{ $service->starts_at->format('H:i') }}@if($primaryName) · Ministro principal: {{ $primaryName }}@endif</div>
                            @if($additionalNames->isNotEmpty())<div class="mt-1 text-xs text-slate-500">Ministros adicionais: {{ $additionalNames->implode(', ') }}</div>@endif
                        </div>
                        <a class="sgml-btn-secondary" href="{{ route('repertoire.show', $service) }}">Abrir repertório</a>
                    </div>
                </div>

                <div class="divide-y divide-slate-100">
                    @if($repertoire?->openingSong)
                        @php
                            $openingVersion = $repertoire->openingSongVersion ?? $repertoire->openingSong->versions->firstWhere('is_default', true) ?? $repertoire->openingSong->versions->first();
                            $openingKey = $repertoire->opening_key ?: $openingVersion?->original_key;
                            $openingMinister = $repertoire->openingMinister?->display_name ?: ($service->additionalMinisters->where('status','published')->isEmpty() ? $primaryName : null);
                            $canEditOpening = in_array($repertoire->id, $editableOpeningIds, true);
                        @endphp
                        <div class="grid gap-3 px-5 py-4 lg:grid-cols-[80px_minmax(0,2fr)_minmax(150px,1fr)_minmax(170px,1fr)] lg:items-center">
                            <span class="sgml-badge bg-violet-50 text-violet-700">Abertura</span>
                            <div>
                                <div class="font-bold text-slate-900">{{ $repertoire->openingSong->title }}</div>
                                <div class="mt-1 text-xs text-slate-500">Ministro: {{ $openingMinister ?: 'a definir' }}</div>
                            </div>
                            <div><span class="text-xs font-bold uppercase tracking-wide text-slate-400">Tom do ensaio</span><div class="mt-1 text-lg font-black text-indigo-700">{{ $openingKey ?: '—' }}</div></div>
                            @if($canEditOpening)
                                <form method="POST" action="{{ route('rehearsals.opening-key.update', $repertoire) }}" class="flex gap-2">@csrf @method('PATCH')
                                    <input name="opening_key" value="{{ $openingKey }}" class="sgml-input min-w-0" maxlength="20" placeholder="Ex.: G">
                                    <button class="sgml-btn-primary shrink-0">Salvar tom</button>
                                </form>
                            @else
                                <div class="text-xs font-semibold text-slate-500">Visível para toda a equipe</div>
                            @endif
                        </div>
                    @endif

                    @foreach($repertoire?->items ?? [] as $repItem)
                        @php
                            $version = $repItem->songVersion ?? $repItem->song?->versions?->firstWhere('is_default', true) ?? $repItem->song?->versions?->first();
                            $key = $repItem->planned_key ?: $version?->original_key;
                            $ministerName = $repItem->minister?->display_name ?: ($service->additionalMinisters->where('status','published')->isEmpty() ? $primaryName : null);
                            $canEditKey = in_array($repItem->id, $editableItemIds, true);
                        @endphp
                        <div id="rehearsal-key-{{ $repItem->id }}" class="grid gap-3 px-5 py-4 lg:grid-cols-[80px_minmax(0,2fr)_minmax(150px,1fr)_minmax(170px,1fr)] lg:items-center">
                            <span class="sgml-badge bg-indigo-50 text-indigo-700">{{ $repItem->position }}</span>
                            <div>
                                <div class="font-bold text-slate-900">{{ $repItem->song?->title ?? 'Louvor' }}</div>
                                <div class="mt-1 text-xs text-slate-500">Ministro: {{ $ministerName ?: 'a definir' }}@if($repItem->notes) · {{ $repItem->notes }}@endif</div>
                            </div>
                            <div><span class="text-xs font-bold uppercase tracking-wide text-slate-400">Tom do ensaio</span><div class="mt-1 text-lg font-black text-indigo-700">{{ $key ?: '—' }}</div></div>
                            @if($canEditKey)
                                <form method="POST" action="{{ route('rehearsals.repertoire-key.update', $repItem) }}" class="flex gap-2">@csrf @method('PATCH')
                                    <input name="planned_key" value="{{ $key }}" class="sgml-input min-w-0" maxlength="20" placeholder="Ex.: G">
                                    <button class="sgml-btn-primary shrink-0">Salvar tom</button>
                                </form>
                            @else
                                <div class="text-xs font-semibold text-slate-500">Visível para toda a equipe</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </article>
        @empty
            <div class="sgml-card border-dashed p-6 text-center text-sm text-slate-500">Nenhum próximo culto com repertório definido. Assim que o ministro adicionar os louvores, os tons aparecerão aqui para os músicos.</div>
        @endforelse
    </div>
</section>

<div class="mb-4 border-t border-slate-200 pt-7">
    <h2 class="text-xl font-black text-slate-950">Biblioteca de estudo contínuo</h2>
    <p class="mt-1 text-sm text-slate-500">Use esta área para louvores que a equipe ainda está aprendendo ou aprimorando, independentemente de um culto específico.</p>
</div>

@perm('rehearsals.manage')
<div class="mb-6 sgml-card p-5">
    <h2 class="font-black">Adicionar louvor ao ensaio</h2>
    <form id="rehearsal-add-form" method="POST" action="{{ route('rehearsals.store') }}" class="mt-4 grid gap-3 lg:grid-cols-[minmax(0,2fr)_180px_minmax(0,2fr)_auto]">
        @csrf
        <div>
            <label class="sgml-label">Buscar louvor</label>
            <input id="rehearsal-song-search" class="sgml-input mb-2" type="search" placeholder="Digite parte do título ou artista" autocomplete="off">
            <label class="sgml-label">Louvor</label>
            <select id="rehearsal-song-select" class="sgml-input" name="song_id" required>
                <option value="">Selecione</option>
                @foreach($songs as $song)
                    @php($songVersion = $song->versions->first(fn($version) => $version->is_default && $version->active) ?? $song->versions->first(fn($version) => $version->active && ($version->youtube_url || $version->original_key)) ?? $song->versions->first(fn($version) => $version->active) ?? $song->versions->first())
                    <option value="{{ $song->id }}" data-key="{{ $songVersion?->original_key }}" data-url="{{ $songVersion?->youtube_url }}">{{ $song->title }} · {{ $song->artist_reference ?: $song->category?->name }}</option>
                @endforeach
            </select>
            <div id="rehearsal-song-preview" class="mt-2 hidden rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
                <span class="font-semibold">Tom:</span> <span id="rehearsal-song-key">não informado</span>
                <a id="rehearsal-song-link" class="ml-3 hidden font-semibold text-indigo-700 hover:underline" target="_blank" rel="noopener noreferrer">Ouvir referência no YouTube ↗</a>
            </div>
        </div>
        <div><label class="sgml-label">Prioridade</label><select class="sgml-input" name="priority"><option value="1">Alta</option><option value="2" selected>Normal</option><option value="3">Baixa</option></select></div>
        <div><label class="sgml-label">Observação</label><input class="sgml-input" name="notes" placeholder="Ex.: revisar ponte, tonalidade, dinâmica..."></div>
        <div class="flex items-end"><button id="rehearsal-add-button" class="sgml-btn-primary w-full"><span data-idle-label>Adicionar</span><span data-loading-label class="hidden">Adicionando...</span></button></div>
    </form>
</div>
@endperm

<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
@forelse($items as $item)
    @php($version = $item->song?->versions?->first(fn($candidate) => $candidate->is_default && $candidate->active) ?? $item->song?->versions?->first(fn($candidate) => $candidate->active && ($candidate->youtube_url || $candidate->original_key)) ?? $item->song?->versions?->first(fn($candidate) => $candidate->active) ?? $item->song?->versions?->first())
    <article class="sgml-card p-5">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0"><h2 class="font-black">{{ $item->song?->title }}</h2><div class="text-sm text-slate-500">{{ $item->song?->artist_reference ?: $version?->artist ?: 'Artista a confirmar' }}</div></div>
            <span class="sgml-badge {{ $item->status==='practicing'?'bg-amber-50 text-amber-700':($item->status==='ready'?'bg-emerald-50 text-emerald-700':'bg-slate-100 text-slate-600') }}">{{ ['practicing'=>'Em ensaio','ready'=>'Pronto','removed'=>'Removido'][$item->status] ?? $item->status }}</span>
        </div>
        <div class="mt-3 flex flex-wrap gap-2 text-xs"><span class="sgml-badge bg-indigo-50 text-indigo-700">{{ $item->song?->category?->name ?? 'Sem categoria' }}</span><span class="sgml-badge bg-slate-100 text-slate-700">Prioridade {{ [1=>'alta',2=>'normal',3=>'baixa'][$item->priority] ?? $item->priority }}</span></div>
        <div class="mt-3 grid gap-2 sm:grid-cols-2">
            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                <div class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Tom</div>
                <div class="mt-0.5 font-bold text-slate-800">{{ $version?->original_key ?: 'Não informado' }}</div>
            </div>
            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                <div class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Referência</div>
                @if($version?->youtube_url)
                    <a class="mt-0.5 inline-flex items-center gap-1 font-bold text-indigo-700 hover:underline" target="_blank" rel="noopener noreferrer" href="{{ $version->youtube_url }}">▶ Ouvir no YouTube <span aria-hidden="true">↗</span></a>
                @else
                    <div class="mt-0.5 text-sm font-semibold text-slate-500">Link não informado</div>
                @endif
            </div>
        </div>
        @if($item->notes)<div class="mt-3 rounded-lg bg-slate-50 p-3 text-sm text-slate-600">{{ $item->notes }}</div>@endif

        @perm('rehearsals.manage')
            @if($item->status==='practicing')
                <form method="POST" action="{{ route('rehearsals.update',$item) }}" class="mt-4 grid gap-2">@csrf @method('PATCH')
                    <input type="hidden" name="status" value="practicing"><select class="sgml-input" name="priority"><option value="1" @selected($item->priority===1)>Alta</option><option value="2" @selected($item->priority===2)>Normal</option><option value="3" @selected($item->priority===3)>Baixa</option></select><input class="sgml-input" name="notes" value="{{ $item->notes }}" placeholder="Observação"><button class="sgml-btn-secondary">Atualizar</button>
                </form>
                <div class="mt-2 grid grid-cols-2 gap-2">
                    <form method="POST" action="{{ route('rehearsals.update',$item) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="ready"><input type="hidden" name="priority" value="{{ $item->priority }}"><input type="hidden" name="notes" value="{{ $item->notes }}"><button class="sgml-btn-primary w-full">Marcar pronto</button></form>
                    <form method="POST" action="{{ route('rehearsals.remove',$item) }}">@csrf @method('DELETE')<button class="sgml-btn-danger w-full">Remover</button></form>
                </div>
            @else
                <form method="POST" action="{{ route('rehearsals.reactivate',$item) }}" class="mt-4">@csrf<button class="sgml-btn-secondary w-full">Voltar para ensaio</button></form>
            @endif
        @endperm
    </article>
@empty
    <div class="sgml-card p-10 text-center text-slate-500 md:col-span-2 xl:col-span-3">Nenhum louvor nesta situação.</div>
@endforelse
</div>
@perm('rehearsals.manage')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('rehearsal-add-form');
    const search = document.getElementById('rehearsal-song-search');
    const select = document.getElementById('rehearsal-song-select');
    const preview = document.getElementById('rehearsal-song-preview');
    const keyLabel = document.getElementById('rehearsal-song-key');
    const link = document.getElementById('rehearsal-song-link');
    const submit = document.getElementById('rehearsal-add-button');
    if (!search || !select) return;

    const catalog = Array.from(select.options).slice(1).map(option => ({
        value: option.value,
        text: option.textContent,
        key: option.dataset.key || '',
        url: option.dataset.url || '',
    }));
    const normalize = value => (value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

    const refreshPreview = () => {
        const option = select.selectedOptions[0];
        if (!option || !option.value || !preview) {
            preview?.classList.add('hidden');
            return;
        }
        preview.classList.remove('hidden');
        if (keyLabel) keyLabel.textContent = option.dataset.key || 'não informado';
        if (link) {
            const url = option.dataset.url || '';
            if (url) {
                link.href = url;
                link.classList.remove('hidden');
            } else {
                link.removeAttribute('href');
                link.classList.add('hidden');
            }
        }
    };

    search.addEventListener('input', () => {
        const term = normalize(search.value.trim());
        const current = select.value;
        select.innerHTML = '<option value="">Selecione</option>';
        catalog.filter(item => term === '' || normalize(item.text).includes(term)).slice(0, 100).forEach(item => {
            const option = document.createElement('option');
            option.value = item.value;
            option.textContent = item.text;
            option.dataset.key = item.key;
            option.dataset.url = item.url;
            select.appendChild(option);
        });
        if (Array.from(select.options).some(option => option.value === current)) select.value = current;
        refreshPreview();
    });
    select.addEventListener('change', refreshPreview);
    refreshPreview();

    form?.addEventListener('submit', () => {
        if (!submit) return;
        submit.disabled = true;
        submit.querySelector('[data-idle-label]')?.classList.add('hidden');
        submit.querySelector('[data-loading-label]')?.classList.remove('hidden');
    });
});
</script>
@endperm
@endsection
