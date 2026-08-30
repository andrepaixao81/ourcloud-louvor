@extends('layouts.app')
@section('content')
<div class="sgml-page-header">
    <div>
        <a href="{{ route('people.index') }}" class="mb-2 inline-flex items-center gap-1 text-sm font-bold text-indigo-700"><x-icon name="chevron-left" class="h-4 w-4"/>Pessoas</a>
        <div class="sgml-page-kicker">Cadastro</div>
        <h1 class="sgml-page-title">{{ $person->exists ? $person->display_name : 'Nova pessoa' }}</h1>
        <p class="sgml-page-description">Dados pessoais e participação no ministério. O acesso ao sistema pode ser gerenciado separadamente quando necessário.</p>
    </div>
    @if($person->exists && auth()->user()?->isAdministrator())
        @if($person->user)<a class="sgml-btn-secondary" href="{{ route('admin.users', ['user'=>$person->user->id]) }}#user-{{ $person->user->id }}"><x-icon name="shield" class="h-4 w-4"/>Gerenciar acesso</a>@else<a class="sgml-btn-secondary" href="{{ route('admin.users', ['person'=>$person->id]) }}"><x-icon name="plus" class="h-4 w-4"/>Conceder acesso</a>@endif
    @endif
</div>

<form method="POST" action="{{ $person->exists ? route('people.update',$person) : route('people.store') }}" class="space-y-6">@csrf @if($person->exists)@method('PATCH')@endif
    <section class="sgml-card p-5 sm:p-6">
        <div class="mb-5 flex items-start gap-3"><div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-700"><x-icon name="user"/></div><div><h2 class="text-lg font-black">Informações básicas</h2><p class="mt-1 text-sm text-slate-500">Dados de contato usados também em avisos e aniversariantes.</p></div></div>
        <div class="grid gap-4 md:grid-cols-2">
            <div><label class="sgml-label">Nome</label><input class="sgml-input" name="first_name" value="{{ old('first_name',$person->first_name) }}" required></div>
            <div><label class="sgml-label">Sobrenome</label><input class="sgml-input" name="last_name" value="{{ old('last_name',$person->last_name) }}"></div>
            <div><label class="sgml-label">Como aparecerá no sistema</label><input class="sgml-input" name="display_name" value="{{ old('display_name',$person->display_name) }}" required><p class="sgml-help">Ex.: João Silva ou Pr. João.</p></div>
            <div><label class="sgml-label">Congregação principal</label><select class="sgml-input" name="congregation_id"><option value="">Não definida</option>@foreach($congregations as $c)<option value="{{ $c->id }}" @selected(old('congregation_id',$person->congregation_id)===$c->id)>{{ $c->name }}</option>@endforeach</select></div>
            <div><label class="sgml-label">E-mail</label><input class="sgml-input" type="email" name="email" value="{{ old('email',$person->email) }}" autocomplete="email"></div>
            <div><label class="sgml-label">Telefone / WhatsApp</label><input class="sgml-input" type="tel" name="phone" value="{{ old('phone',$person->phone) }}" data-br-phone inputmode="tel" placeholder="(21) 99999-9999" maxlength="16"></div>
            <div><label class="sgml-label">Data de nascimento</label><input class="sgml-input" type="date" name="birth_date" value="{{ old('birth_date',optional($person->birth_date)->format('Y-m-d')) }}"><p class="sgml-help">O ano não é exibido na seção de aniversariantes.</p></div>
            <div><label class="sgml-label">Data de entrada no ministério</label><input class="sgml-input" type="date" name="joined_at" value="{{ old('joined_at',optional($person->joined_at)->format('Y-m-d')) }}"></div>
        </div>
    </section>

    <section class="sgml-card p-5 sm:p-6">
        <div class="mb-5 flex items-start gap-3"><div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-700"><x-icon name="mic"/></div><div><h2 class="text-lg font-black">Participação no ministério</h2><p class="mt-1 text-sm text-slate-500">Marque as funções que essa pessoa exerce.</p></div></div>
        @php($selectedRoles=collect(old('ministry_roles',$person->exists?$person->ministryRoles->pluck('id')->all():[])))
        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($roles as $role)
                <label class="flex cursor-pointer items-center gap-3 rounded-2xl border border-slate-200 p-4 transition hover:border-indigo-200 hover:bg-indigo-50/30"><input class="h-4 w-4" type="checkbox" name="ministry_roles[]" value="{{ $role->id }}" data-minister-role="{{ $role->is_minister_role?'1':'0' }}" @checked($selectedRoles->contains($role->id))><span class="min-w-0"><span class="font-bold text-slate-900">{{ $role->name }}</span>@if($role->is_minister_role)<span class="mt-1 block text-xs font-semibold text-indigo-600">Pode entrar na escala</span>@endif</span></label>
            @endforeach
        </div>
    </section>

    @php($selectedServiceTypes=collect(old('minister_service_types',$selectedServiceTypeIds??[])))
    <section id="minister-service-types" class="sgml-card p-5 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 class="text-lg font-black">Em quais cultos pode ministrar?</h2><p class="mt-1 max-w-3xl text-sm text-slate-500">Esta seção só se aplica a pessoas com função de ministro. Selecione os tipos permitidos para o gerador de escala.</p></div><div class="flex gap-2"><button type="button" id="select-common-service-types" class="sgml-btn-secondary">Marcar comuns</button><button type="button" id="clear-service-types" class="sgml-btn-tertiary">Limpar</button></div></div>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($serviceTypes as $serviceType)
                <label class="cursor-pointer rounded-2xl border border-slate-200 p-4 text-sm transition hover:border-indigo-200 hover:bg-indigo-50/30"><div class="flex items-start gap-3"><input class="mt-1" type="checkbox" name="minister_service_types[]" value="{{ $serviceType->id }}" data-service-type data-special="{{ $serviceType->requires_authorization?'1':'0' }}" @checked($selectedServiceTypes->contains($serviceType->id))><span><span class="font-bold text-slate-900">{{ $serviceType->name }}</span>@if($serviceType->requires_authorization)<span class="mt-1 block text-xs font-bold text-amber-700">Exige autorização específica</span>@else<span class="mt-1 block text-xs text-slate-500">Culto comum</span>@endif</span></div></label>
            @empty<div class="text-sm text-slate-500">Nenhum tipo de culto ativo cadastrado.</div>@endforelse
        </div>
        <div id="minister-service-types-warning" class="mt-4 hidden rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"><div class="flex gap-2"><x-icon name="alert" class="h-5 w-5 shrink-0"/><span>Essa pessoa não possui função marcada como ministro. As habilitações de culto só serão gravadas quando houver uma função elegível para escala.</span></div></div>
    </section>

    <details class="sgml-card p-5 sm:p-6" {{ old('admin_notes') ? 'open' : '' }}>
        <summary class="cursor-pointer list-none"><div class="flex items-center justify-between gap-3"><div><h2 class="font-black">Informações administrativas</h2><p class="mt-1 text-sm text-slate-500">Status e observações internas. Use somente quando necessário.</p></div><span class="sgml-badge-muted">Avançado</span></div></summary>
        <div class="mt-5 grid gap-4 border-t border-slate-100 pt-5 md:grid-cols-2"><div><label class="sgml-label">Status</label><select class="sgml-input" name="status"><option value="active" @selected(old('status',$person->status?:'active')==='active')>Ativo</option><option value="inactive" @selected(old('status',$person->status)==='inactive')>Inativo</option></select></div><div class="md:col-span-2"><label class="sgml-label">Observações administrativas</label><textarea class="sgml-input" rows="4" name="admin_notes">{{ old('admin_notes',$person->admin_notes) }}</textarea></div></div>
    </details>

    <div class="sticky bottom-[4.7rem] z-20 -mx-4 flex gap-3 border-t border-slate-200 bg-white/95 px-4 py-3 backdrop-blur lg:static lg:mx-0 lg:border-0 lg:bg-transparent lg:px-0 lg:py-0"><button class="sgml-btn-primary flex-1 sm:flex-none" data-loading-label="Salvando...">Salvar pessoa</button><a class="sgml-btn-secondary" href="{{ route('people.index') }}">Cancelar</a></div>
</form>

<script>
document.addEventListener('DOMContentLoaded',()=>{const roles=[...document.querySelectorAll('[data-minister-role="1"]')],types=[...document.querySelectorAll('[data-service-type]')],warning=document.getElementById('minister-service-types-warning');const refresh=()=>warning?.classList.toggle('hidden',roles.some(i=>i.checked));roles.forEach(i=>i.addEventListener('change',refresh));refresh();document.getElementById('select-common-service-types')?.addEventListener('click',()=>types.forEach(i=>{if(i.dataset.special!=='1')i.checked=true;}));document.getElementById('clear-service-types')?.addEventListener('click',()=>types.forEach(i=>i.checked=false));});
</script>
@endsection
