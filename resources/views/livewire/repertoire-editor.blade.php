<div class="space-y-6">
    @if($actionFeedback !== '')
        <div aria-live="polite" x-data="{ show: true }" x-init="setTimeout(() => show = false, 5500)" x-show="show" x-transition class="fixed bottom-5 right-5 z-[70] flex max-w-sm items-start gap-3 rounded-xl border border-emerald-200 bg-white px-4 py-3 text-sm text-emerald-900 shadow-xl">
            <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-100 font-black text-emerald-700">✓</span>
            <div class="min-w-0 flex-1"><div class="font-black">Operação concluída</div><div>{{ $actionFeedback }}</div></div>
            <button type="button" class="ml-1 text-lg leading-none text-slate-400 hover:text-slate-700" x-on:click="show=false" aria-label="Fechar aviso">×</button>
        </div>
    @endif
    <div data-repertoire-livewire-warning class="hidden rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-800">Os controles interativos do repertório não foram carregados. Atualize a página; se persistir, peça ao administrador para validar o runtime Livewire.</div>
    @if(session('status'))
        <div class="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="sgml-card p-5">
        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold">Repertório do culto</h2>
                <p class="text-sm text-slate-500">Revisão {{ $repertoire->revision_number }} · as alterações salvas passam a valer imediatamente na escala publicada.</p>
            </div>
            @if($serviceEnded)
                <span class="sgml-badge bg-amber-50 text-amber-800">Culto encerrado · correções atualizam o histórico</span>
            @endif
        </div>

        @if($canManage)
            <div class="mb-6 rounded-2xl border border-indigo-100 bg-indigo-50/60 p-4">
                <div class="mb-3 text-xs font-black uppercase tracking-[.14em] text-indigo-600">Como montar o repertório</div>
                <div class="grid gap-2 md:grid-cols-3">
                    <div class="flex items-start gap-3 rounded-xl bg-white p-3 ring-1 ring-indigo-100">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-black text-indigo-700">1</span>
                        <div><div class="font-black text-slate-900">Abertura <span class="font-semibold text-slate-400">(opcional)</span></div><p class="mt-0.5 text-xs text-slate-500">Um louvor antes da sequência principal. Ele não entra na numeração.</p></div>
                    </div>
                    <div class="flex items-start gap-3 rounded-xl bg-white p-3 ring-1 ring-indigo-100">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-violet-100 text-xs font-black text-violet-700">2</span>
                        <div><div class="font-black text-slate-900">Louvores da ministração</div><p class="mt-0.5 text-xs text-slate-500">Adicione Louvor 1, Louvor 2, Louvor 3... na ordem que serão ministrados.</p></div>
                    </div>
                    <div class="flex items-start gap-3 rounded-xl bg-white p-3 ring-1 ring-indigo-100">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-black text-emerald-700">3</span>
                        <div><div class="font-black text-slate-900">Conferir e avisar equipe</div><p class="mt-0.5 text-xs text-slate-500">Revise a ordem, ministros e tons. Depois confirme para avisar os músicos.</p></div>
                    </div>
                </div>
                <p class="mt-3 text-xs text-slate-500">Cada alteração salva já aparece na escala e na agenda. O último tom usado por cada ministro é sugerido automaticamente.</p>
            </div>
        @endif

        <details class="mb-6 rounded-2xl border border-sky-200 bg-sky-50/40 p-4" @if(!$repertoire->openingSong) open @endif>
            <summary class="cursor-pointer list-none">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-sky-100 px-2 py-1 text-[10px] font-black uppercase tracking-wide text-sky-700">Etapa 1</span>
                            <span class="rounded-full bg-white px-2 py-1 text-[10px] font-bold text-slate-500 ring-1 ring-slate-200">Opcional</span>
                        </div>
                        <div class="mt-2 font-black text-slate-950">Abertura</div>
                        <p class="mt-1 text-xs text-slate-500">Se o culto tiver um louvor antes da sequência principal, defina aqui. <strong>Ele não será contado como Louvor 1.</strong></p>
                    </div>
                    <span class="text-sm font-semibold text-sky-700">{{ $repertoire->openingSong ? 'Alterar abertura' : 'Definir abertura' }}</span>
                </div>
            </summary>

            @if($canManage)
                <div class="mt-4 border-t border-sky-100 pt-4">
                    <div class="mb-4 grid gap-3 md:grid-cols-2">
                        <div>
                            <label class="sgml-label">Buscar na biblioteca</label>
                            <input wire:model.live.debounce.300ms="openingSongSearch" class="sgml-input" placeholder="Ex.: Canção de Gratidão">
                        </div>
                        <div>
                            <label class="sgml-label">Categoria</label>
                            <select wire:model.change.live="openingCategoryId" class="sgml-input">
                                <option value="">Todas as categorias</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    @if(trim($openingSongSearch) !== '' && $openingSearchResultsOpen)
                        <div class="mb-4 rounded-xl border border-slate-200 bg-white p-3">
                            <div class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">Resultados da busca</div>
                            <div class="max-h-56 space-y-2 overflow-y-auto">
                                @forelse($openingSongs as $song)
                                    @php
                                        $openingSearchVersion = $song->versions->firstWhere('is_default', true) ?? $song->versions->first();
                                        $openingSearchArtist = $song->artist_reference ?: ($openingSearchVersion?->artist);
                                    @endphp
                                    <button type="button" wire:click="selectOpeningSong('{{ $song->id }}')" class="flex w-full items-center justify-between gap-3 rounded-lg border border-slate-200 px-3 py-2 text-left hover:border-indigo-300 hover:bg-indigo-50">
                                        <span>
                                            <span class="block font-semibold text-slate-900">{{ $song->title }}</span>
                                            <span class="block text-xs text-slate-500">{{ $song->category?->name ?? 'Sem categoria' }}@if($openingSearchArtist) · {{ $openingSearchArtist }}@endif</span>
                                        </span>
                                        <span class="shrink-0 text-xs font-bold text-indigo-700">Selecionar</span>
                                    </button>
                                @empty
                                    <div class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">Nenhum louvor encontrado. Use “Cadastrar novo louvor como abertura”.</div>
                                @endforelse
                            </div>
                        </div>
                    @endif

                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                        <div class="md:col-span-2">
                            <label class="sgml-label">Louvor de abertura</label>
                            <select wire:model.change="openingSongId" class="sgml-input">
                                <option value="">Sem louvor de abertura</option>
                                @foreach($openingSongs as $song)
                                    @php
                                        $openingDefaultVersion = $song->versions->firstWhere('is_default', true) ?? $song->versions->first();
                                        $openingOptionLabel = $song->title.' · '.($song->category?->name ?? 'Sem categoria');
                                        $openingArtist = $song->artist_reference ?: ($openingDefaultVersion?->artist);
                                        if ($openingArtist) $openingOptionLabel .= ' · '.$openingArtist;
                                        if ($openingDefaultVersion?->original_key) $openingOptionLabel .= ' · Tom '.$openingDefaultVersion->original_key;
                                    @endphp
                                    <option value="{{ $song->id }}">{{ $openingOptionLabel }}</option>
                                @endforeach
                            </select>
                            @if($errors->has('openingSongId'))
                                <p class="mt-1 text-xs text-rose-600">{{ $errors->first('openingSongId') }}</p>
                            @endif
                        </div>
                        <div><label class="sgml-label">Quem ministrará</label><select wire:model.change="openingMinisterId" class="sgml-input"><option value="">Definir depois</option>@foreach($ministerOptions as $minister)<option value="{{ $minister->id }}">{{ $minister->display_name }}</option>@endforeach</select></div>
                        <div><label class="sgml-label">Tom deste ministro</label><input wire:model="openingKey" class="sgml-input" placeholder="Ex.: G"><p class="mt-1 text-[11px] text-slate-500">O último tom salvo para este ministro será lembrado.</p></div>
                        <div><label class="sgml-label">Link específico</label><input wire:model="openingYoutubeUrl" class="sgml-input" placeholder="https://..."></div>
                        <div class="md:col-span-2 xl:col-span-5"><label class="sgml-label">Nota da abertura <span class="font-normal text-slate-400">(opcional)</span></label><textarea wire:model="openingNotes" class="sgml-input" rows="2" maxlength="1000" placeholder="Ex.: somente voz e teclado"></textarea></div>
                        <div class="md:col-span-2 xl:col-span-5"><button type="button" wire:click="saveOpening" wire:loading.attr="disabled" wire:target="saveOpening" class="sgml-btn-primary"><span wire:loading.remove wire:target="saveOpening">Definir como louvor de abertura</span><span wire:loading wire:target="saveOpening">Salvando...</span></button></div>
                    </div>

                    <details class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50/50 p-4" @if($openingSongs->isEmpty() && trim($openingSongSearch) !== '') open @endif>
                        <summary class="cursor-pointer font-bold text-emerald-900">Não encontrou? Cadastrar novo louvor como abertura</summary>
                        <p class="mt-2 text-sm text-emerald-800">O louvor será cadastrado na biblioteca e definido imediatamente como abertura, sem aprovação.</p>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <div><label class="sgml-label">Título</label><input wire:model="newOpeningSongTitle" class="sgml-input" required></div>
                            <div><label class="sgml-label">Artista/referência</label><input wire:model="newOpeningSongArtist" class="sgml-input"></div>
                            <div><label class="sgml-label">Categoria</label><select wire:model="newOpeningSongCategoryId" class="sgml-input"><option value="">Sem categoria</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></div>
                            <div><label class="sgml-label">Quem ministrará</label><select wire:model="newOpeningSongMinisterId" class="sgml-input"><option value="">Definir depois</option>@foreach($ministerOptions as $minister)<option value="{{ $minister->id }}">{{ $minister->display_name }}</option>@endforeach</select></div>
                            <div><label class="sgml-label">Tom deste ministro</label><input wire:model="newOpeningSongKey" class="sgml-input" placeholder="Ex.: G"></div>
                            <div class="sm:col-span-2"><label class="sgml-label">Link de referência</label><input wire:model="newOpeningSongUrl" class="sgml-input" placeholder="https://..."></div>
                            <div class="sm:col-span-2"><label class="sgml-label">Observação</label><textarea wire:model="newOpeningSongNotes" class="sgml-input" rows="2"></textarea></div>
                            <div class="sm:col-span-2"><button type="button" wire:click="createOpeningSongAndSet" wire:loading.attr="disabled" wire:target="createOpeningSongAndSet" class="sgml-btn-primary"><span wire:loading.remove wire:target="createOpeningSongAndSet">Cadastrar e definir como abertura</span><span wire:loading wire:target="createOpeningSongAndSet">Cadastrando...</span></button></div>
                        </div>
                    </details>
                </div>
            @elseif($repertoire->openingSong)
                <div class="mt-4 border-t border-sky-100 pt-4 text-sm">
                    <strong>{{ $repertoire->openingSong->title }}</strong>
                    @if($repertoire->openingMinister)<span class="text-slate-500"> · {{ $repertoire->openingMinister->display_name }}</span>@endif
                    @if($repertoire->opening_key)<span class="sgml-badge-info ml-1">Tom {{ $repertoire->opening_key }}</span>@endif
                    @if($repertoire->opening_notes)<div class="mt-1 text-xs text-slate-500">{{ $repertoire->opening_notes }}</div>@endif
                </div>
            @else
                <p class="mt-4 border-t border-indigo-100 pt-4 text-sm text-slate-500">Nenhum louvor de abertura informado.</p>
            @endif
        </details>

        @if($canManage)
            <div class="mb-6 rounded-2xl border border-violet-200 bg-white p-4 shadow-sm">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3 border-b border-violet-100 pb-4">
                <div>
                    <div class="mb-2 inline-flex items-center gap-2">
                        <span class="rounded-full bg-violet-100 px-2 py-1 text-[10px] font-black uppercase tracking-wide text-violet-700">Etapa 2</span>
                        <span class="text-xs font-semibold text-slate-400">Principal</span>
                    </div>
                    <h3 class="font-black text-slate-950">Adicionar louvores da ministração</h3>
                    <p class="mt-1 text-sm text-slate-500">Aqui entram somente os louvores numerados: <strong>Louvor 1, Louvor 2, Louvor 3...</strong></p>
                </div>
                <div class="rounded-xl bg-violet-50 px-3 py-2 text-xs font-semibold text-violet-700">{{ $items->count() }} louvor(es) principal(is)</div>
            </div>
            <div class="mb-4 grid gap-3 md:grid-cols-2">
                <div><label class="sgml-label">Buscar</label><input wire:model.live.debounce.300ms="songSearch" class="sgml-input" placeholder="Ex.: Canção de Gratidão"></div>
                <div><label class="sgml-label">Categoria</label><select wire:model.change.live="categoryId" class="sgml-input"><option value="">Todas as categorias</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></div>
            </div>
            @if(trim($songSearch) !== '' && $songSearchResultsOpen)
                <div class="mb-4 rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <div class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">Resultados da busca</div>
                    <div class="max-h-56 space-y-2 overflow-y-auto">
                        @forelse($songs as $song)
                            @php
                                $searchVersion = $song->versions->firstWhere('is_default', true) ?? $song->versions->first();
                                $searchArtist = $song->artist_reference ?: ($searchVersion?->artist);
                            @endphp
                            <button type="button" wire:click="selectSong('{{ $song->id }}')" class="flex w-full items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2 text-left hover:border-indigo-300 hover:bg-indigo-50">
                                <span>
                                    <span class="block font-semibold text-slate-900">{{ $song->title }}</span>
                                    <span class="block text-xs text-slate-500">{{ $song->category?->name ?? 'Sem categoria' }}@if($searchArtist) · {{ $searchArtist }}@endif</span>
                                </span>
                                <span class="shrink-0 text-xs font-bold text-indigo-700">Selecionar</span>
                            </button>
                        @empty
                            <div class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">Nenhum louvor encontrado. Cadastre o louvor abaixo e ele será incluído imediatamente.</div>
                        @endforelse
                    </div>
                </div>
            @endif

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <div class="md:col-span-2 xl:col-span-2">
                    <label class="sgml-label">Louvor</label>
                    <select wire:model.change="songId" class="sgml-input">
                        <option value="">Selecione entre {{ $songs->count() }} resultado(s)</option>
                        @foreach($songs as $song)
                            @php
                                $defaultVersion = $song->versions->firstWhere('is_default', true) ?? $song->versions->first();
                                $optionLabel = $song->title.' · '.($song->category?->name ?? 'Sem categoria');
                                $optionArtist = $song->artist_reference ?: ($defaultVersion?->artist);
                                if ($optionArtist) $optionLabel .= ' · '.$optionArtist;
                                if ($defaultVersion?->original_key) $optionLabel .= ' · Tom '.$defaultVersion->original_key;
                            @endphp
                            <option value="{{ $song->id }}">{{ $optionLabel }}</option>
                        @endforeach
                    </select>
                    @if($errors->has('songId'))<p class="mt-1 text-xs text-rose-600">{{ $errors->first('songId') }}</p>@endif
                </div>
                <div><label class="sgml-label">Quem ministrará</label><select wire:model.change="songMinisterId" class="sgml-input"><option value="">Definir depois</option>@foreach($ministerOptions as $minister)<option value="{{ $minister->id }}">{{ $minister->display_name }}</option>@endforeach</select></div>
                <div><label class="sgml-label">Tom deste ministro</label><input wire:model="plannedKey" class="sgml-input" placeholder="Ex.: G"><p class="mt-1 text-[11px] text-slate-500">Sugere o último tom usado por esta pessoa.</p></div>
                <div><label class="sgml-label">Link específico</label><input wire:model="specificYoutubeUrl" class="sgml-input" placeholder="https://..."></div>
                <div class="md:col-span-2 xl:col-span-5"><label class="sgml-label">Nota <span class="font-normal text-slate-400">(opcional)</span></label><textarea wire:model="itemNote" class="sgml-input" rows="2" maxlength="1000"></textarea></div>
                <div class="md:col-span-2 xl:col-span-5">
                    <button type="button" wire:click="addSelectedSong" wire:loading.attr="disabled" wire:target="addSelectedSong" class="sgml-btn-primary" @disabled($songId === '')>
                        <span wire:loading.remove wire:target="addSelectedSong">Adicionar louvor ao repertório</span>
                        <span wire:loading wire:target="addSelectedSong">Adicionando...</span>
                    </button>
                    <p wire:loading wire:target="addSelectedSong" class="mt-2 text-xs font-semibold text-indigo-700">Aguarde a confirmação antes de continuar.</p>
                    @if($errors->has('addSong'))<p class="mt-2 text-sm font-semibold text-rose-600">{{ $errors->first('addSong') }}</p>@endif
                </div>
            </div>

            <details class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50/50 p-4" @if($songs->isEmpty() && trim($songSearch) !== '') open @endif>
                <summary class="cursor-pointer font-bold text-emerald-900">Não encontrou? Cadastrar novo louvor agora</summary>
                <p class="mt-2 text-sm text-emerald-800">O ministro pode cadastrar o louvor diretamente. Ele entra na biblioteca e neste repertório imediatamente, sem autorização da liderança ou administração.</p>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div><label class="sgml-label">Título</label><input wire:model="newSongTitle" class="sgml-input" required></div>
                    <div><label class="sgml-label">Artista/referência</label><input wire:model="newSongArtist" class="sgml-input"></div>
                    <div><label class="sgml-label">Categoria</label><select wire:model="newSongCategoryId" class="sgml-input"><option value="">Sem categoria</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></div>
                    <div><label class="sgml-label">Quem ministrará</label><select wire:model="newSongMinisterId" class="sgml-input"><option value="">Definir depois</option>@foreach($ministerOptions as $minister)<option value="{{ $minister->id }}">{{ $minister->display_name }}</option>@endforeach</select></div>
                    <div><label class="sgml-label">Tom deste ministro</label><input wire:model="newSongKey" class="sgml-input" placeholder="Ex.: G"></div>
                    <div class="sm:col-span-2"><label class="sgml-label">Link de referência</label><input wire:model="newSongUrl" class="sgml-input" placeholder="https://..."></div>
                    <div class="sm:col-span-2"><label class="sgml-label">Observação</label><textarea wire:model="newSongNotes" class="sgml-input" rows="2"></textarea></div>
                    <div class="sm:col-span-2"><button type="button" wire:click="createSongAndAdd" wire:loading.attr="disabled" wire:target="createSongAndAdd" class="sgml-btn-primary"><span wire:loading.remove wire:target="createSongAndAdd">Cadastrar e adicionar ao repertório</span><span wire:loading wire:target="createSongAndAdd">Cadastrando...</span></button></div>
                </div>
            </details>
        </div>
    @endif

        <div class="mb-4 rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="mb-2 inline-flex rounded-full bg-emerald-100 px-2 py-1 text-[10px] font-black uppercase tracking-wide text-emerald-700">Etapa 3 · Ordem final</div>
                    <h3 class="font-black text-slate-950">Confira como o repertório será apresentado à equipe</h3>
                    <p class="mt-1 text-sm text-slate-500">A abertura aparece separada. A sequência principal começa sempre em <strong>Louvor 1</strong>.</p>
                </div>
                <div class="text-xs font-semibold text-slate-500">{{ ($repertoire->openingSong ? 1 : 0) + $items->count() }} item(ns) no total</div>
            </div>

            <div class="mt-4 space-y-2">
                @if($repertoire->openingSong)
                    @php
                        $openingPreviewVersion = $repertoire->openingSong->versions->firstWhere('is_default', true) ?? $repertoire->openingSong->versions->first();
                        $openingPreviewKey = $repertoire->opening_key ?: $openingPreviewVersion?->original_key;
                    @endphp
                    <div class="flex flex-wrap items-center gap-3 rounded-xl border border-sky-200 bg-white p-3">
                        <span class="rounded-lg bg-sky-100 px-2.5 py-1 text-xs font-black text-sky-700">ABERTURA</span>
                        <div class="min-w-0 flex-1"><div class="font-black text-slate-900">{{ $repertoire->openingSong->title }}</div><div class="mt-0.5 text-xs text-slate-500">Não entra na numeração dos louvores principais.</div></div>
                        @if($repertoire->openingMinister)<span class="sgml-badge bg-violet-50 text-violet-700">{{ $repertoire->openingMinister->display_name }}</span>@endif
                        @if($openingPreviewKey)<span class="sgml-badge-info">Tom {{ $openingPreviewKey }}</span>@endif
                    </div>
                @else
                    <div class="rounded-xl border border-dashed border-sky-200 bg-white px-3 py-2 text-xs text-slate-500"><strong>Abertura:</strong> não definida. Isso é opcional.</div>
                @endif
            </div>
        </div>

        <div class="mb-3 flex items-end justify-between gap-3">
            <div><h3 class="font-black">Louvores principais</h3><p class="text-xs text-slate-500">Esta é a sequência numerada que será ministrada após a abertura.</p></div>
            @if($items->isNotEmpty())<span class="text-xs font-semibold text-violet-700">Arraste pelo ☰ para mudar a ordem</span>@endif
        </div>

        <ul wire:sort="handleSort" class="space-y-3">
            @forelse($items as $item)
                @php
                    $itemDefaultVersion = $item->song->versions->firstWhere('is_default', true) ?? $item->song->versions->first();
                    $itemArtist = $item->song->artist_reference ?: ($itemDefaultVersion?->artist);
                @endphp
                <li wire:key="rep-{{ $item->id }}" wire:sort:item="{{ $item->id }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-center gap-3">
                        @if($canManage)<span wire:sort:handle class="cursor-grab text-slate-400">☰</span>@endif
                        <span class="shrink-0 rounded-lg bg-violet-100 px-2.5 py-1 text-xs font-black text-violet-700">LOUVOR {{ $loop->iteration }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="font-semibold">{{ $item->song->title }}</div>
                            <div class="mt-0.5 text-xs text-slate-500">{{ $item->song->category?->name ?? 'Sem categoria' }}@if($itemArtist) · {{ $itemArtist }}@endif</div>
                            <div class="mt-1 flex flex-wrap gap-2">@if($item->minister)<span class="sgml-badge bg-violet-50 text-violet-700">Ministro: {{ $item->minister->display_name }}</span>@else<span class="sgml-badge bg-amber-50 text-amber-800">Ministro ainda não definido</span>@endif @if($item->planned_key)<span class="sgml-badge-info">Tom {{ $item->planned_key }}</span>@endif</div>
                        </div>
                        @if(! $canManage && $item->specific_youtube_url)<a class="text-sm text-indigo-600" target="_blank" rel="noopener" href="{{ $item->specific_youtube_url }}">Referência</a>@endif
                        @if($canManage)<button type="button" wire:click="remove('{{ $item->id }}')" wire:confirm="Remover este louvor?" class="text-sm font-semibold text-rose-600">Remover</button>@endif
                    </div>

                    @if($canManage)
                        <div class="mt-3 grid gap-2 md:grid-cols-[minmax(180px,1fr)_140px_minmax(220px,2fr)_auto]">
                            <div><label class="sgml-label">Quem ministrará</label><select wire:model.change="itemMinisterIds.{{ $item->id }}" class="sgml-input"><option value="">Definir depois</option>@foreach($ministerOptions as $minister)<option value="{{ $minister->id }}">{{ $minister->display_name }}</option>@endforeach</select></div>
                            <div><label class="sgml-label">Tom deste ministro</label><input wire:model="itemKeys.{{ $item->id }}" class="sgml-input" placeholder="Ex.: G"></div>
                            <div><label class="sgml-label">Link de referência</label><input wire:model="itemLinks.{{ $item->id }}" class="sgml-input" placeholder="https://..."></div>
                            <div class="self-end"><button type="button" wire:click="updateItem('{{ $item->id }}')" class="sgml-btn-secondary">Salvar tom</button></div>
                            <div class="md:col-span-4"><label class="sgml-label">Nota do louvor <span class="font-normal text-slate-400">(opcional)</span></label><textarea wire:model="itemNotes.{{ $item->id }}" class="sgml-input" rows="2" maxlength="1000" placeholder="Ex.: repetir refrão 2x"></textarea></div>
                        </div>
                    @else
                        <div class="mt-2 text-xs text-slate-500">Ministro: {{ $item->minister?->display_name ?: 'não definido' }} · Tom: {{ $item->planned_key ?: 'não informado' }}@if($item->notes) · {{ $item->notes }}@endif</div>
                    @endif
                </li>
            @empty
                <li class="rounded-2xl border border-dashed border-violet-200 bg-violet-50/30 p-8 text-center">
                    <div class="font-black text-slate-700">Nenhum louvor principal adicionado</div>
                    <p class="mt-1 text-sm text-slate-500">Use a Etapa 2 acima para adicionar Louvor 1, Louvor 2, Louvor 3...</p>
                </li>
            @endforelse
        </ul>

        <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4">
            <div class="font-black">Observações</div>
            <p class="mb-3 text-xs text-slate-500">Observações gerais ao final do repertório.</p>
            @if($canManage)
                <div>
                    <textarea wire:model="finalNotes" class="sgml-input" rows="3" maxlength="3000" placeholder="Ex.: orientação para transições, momento de oração, encerramento..."></textarea>
                    <button type="button" wire:click="saveFinalNotes" wire:loading.attr="disabled" wire:target="saveFinalNotes" class="sgml-btn-secondary mt-3"><span wire:loading.remove wire:target="saveFinalNotes">Salvar observações</span><span wire:loading wire:target="saveFinalNotes">Salvando...</span></button>
                </div>
            @else
                <p class="text-sm text-slate-700">{{ $repertoire->final_notes ?: 'Nenhuma observação.' }}</p>
            @endif
        </div>

        @if($canManage)
            @php $hasUnannouncedChanges = (int)($repertoire->announced_revision ?? 0) !== (int)$repertoire->revision_number; @endphp
            <div class="mt-5 rounded-xl border {{ $hasUnannouncedChanges ? 'border-indigo-200 bg-indigo-50' : 'border-emerald-200 bg-emerald-50' }} p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="font-black">Repertório pronto? Avisar a equipe</div>
                        <p class="mt-1 text-sm text-slate-600">As alterações continuam sendo salvas e refletidas na agenda imediatamente. Quando a ordem, os ministros e os tons estiverem conferidos, use este botão para avisar os músicos que o repertório está pronto.</p>
                        @if(!$hasUnannouncedChanges && $repertoire->announced_at)
                            <p class="mt-1 text-xs font-semibold text-emerald-700">Revisão {{ $repertoire->announced_revision }} confirmada em {{ $repertoire->announced_at->format('d/m/Y H:i') }}.</p>
                        @endif
                    </div>
                    <button type="button" wire:click="confirmRepertoire" wire:confirm="Confirmar este repertório e avisar a equipe?" class="sgml-btn-primary" @disabled(!$hasUnannouncedChanges)>
                        {{ $hasUnannouncedChanges ? 'Confirmar e avisar equipe' : 'Repertório já confirmado' }}
                    </button>
                </div>
            </div>
        @endif
    </div>

</div>
