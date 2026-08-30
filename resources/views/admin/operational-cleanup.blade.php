@extends('layouts.app')
@section('content')
<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <a href="{{ route('admin.index') }}" class="text-sm font-semibold text-indigo-600">← Administração</a>
        <div class="mt-3 text-xs font-black uppercase tracking-[0.18em] text-indigo-600">Manutenção segura</div>
        <h1 class="mt-1 text-2xl font-black">Limpar dados de testes das escalas</h1>
        <p class="mt-1 max-w-3xl text-sm text-slate-500">Use esta rotina quando uma implantação de homologação trouxe escalas, trocas, repertórios ou ocorrências recorrentes de teste. O sistema cria um backup completo antes da limpeza.</p>
    </div>
    <span class="sgml-badge bg-rose-50 text-rose-700">Somente Administrador</span>
</div>

<div class="grid gap-4 lg:grid-cols-2">
    <section class="sgml-card p-5 sm:p-6">
        <div class="flex items-start gap-3">
            <div class="rounded-xl bg-emerald-50 p-2 text-emerald-700"><x-icon name="check"/></div>
            <div>
                <h2 class="text-lg font-black">O que será preservado</h2>
                <p class="mt-1 text-sm text-slate-500">Cadastros e configurações não fazem parte da limpeza operacional.</p>
            </div>
        </div>
        <div class="mt-5 grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl border border-slate-200 p-4"><div class="text-2xl font-black">{{ $preview['users'] }}</div><div class="text-sm text-slate-500">Usuários cadastrados</div></div>
            <div class="rounded-xl border border-slate-200 p-4"><div class="text-2xl font-black">{{ $preview['people'] }}</div><div class="text-sm text-slate-500">Pessoas</div></div>
            <div class="rounded-xl border border-slate-200 p-4"><div class="text-2xl font-black">{{ $preview['songs'] }}</div><div class="text-sm text-slate-500">Louvores da biblioteca</div></div>
            <div class="rounded-xl border border-slate-200 p-4"><div class="text-2xl font-black">{{ $preview['rehearsals'] + $preview['rehearsal_songs'] }}</div><div class="text-sm text-slate-500">Ensaios e louvores em ensaio</div></div>
            <div class="rounded-xl border border-slate-200 p-4"><div class="text-2xl font-black">{{ $preview['active_rules'] }}</div><div class="text-sm text-slate-500">Regras ativas dos cultos</div></div>
            <div class="rounded-xl border border-slate-200 p-4"><div class="text-2xl font-black">{{ $preview['remembered_keys'] }}</div><div class="text-sm text-slate-500">Tons lembrados por ministro</div></div>
            <div class="rounded-xl border border-slate-200 p-4 sm:col-span-2"><div class="text-sm font-bold">Também permanecem</div><div class="mt-1 text-sm text-slate-500">Tipos de culto, locais, congregações, disponibilidade, permissões e configurações.</div></div>
        </div>
    </section>

    <section class="sgml-card border-rose-200 p-5 sm:p-6">
        <div class="flex items-start gap-3">
            <div class="rounded-xl bg-rose-50 p-2 text-rose-700"><x-icon name="alert"/></div>
            <div>
                <h2 class="text-lg font-black">O que será removido</h2>
                <p class="mt-1 text-sm text-slate-500">São dados operacionais recriáveis e resíduos trazidos das escalas de teste.</p>
            </div>
        </div>
        <div class="mt-5 grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl border border-rose-100 bg-rose-50/40 p-4"><div class="text-2xl font-black text-rose-700">{{ $preview['assignments'] }}</div><div class="text-sm text-slate-600">Atribuições de escala</div></div>
            <div class="rounded-xl border border-rose-100 bg-rose-50/40 p-4"><div class="text-2xl font-black text-rose-700">{{ $preview['swaps'] }}</div><div class="text-sm text-slate-600">Trocas</div></div>
            <div class="rounded-xl border border-rose-100 bg-rose-50/40 p-4"><div class="text-2xl font-black text-rose-700">{{ $preview['repertoires'] }}</div><div class="text-sm text-slate-600">Repertórios planejados</div></div>
            <div class="rounded-xl border border-rose-100 bg-rose-50/40 p-4"><div class="text-2xl font-black text-rose-700">{{ $preview['executions'] }}</div><div class="text-sm text-slate-600">Execuções de culto de teste</div></div>
            <div class="rounded-xl border border-rose-100 bg-rose-50/40 p-4"><div class="text-2xl font-black text-rose-700">{{ $preview['recurring_services'] }}</div><div class="text-sm text-slate-600">Ocorrências recorrentes materializadas</div></div>
            <div class="rounded-xl border border-rose-100 bg-rose-50/40 p-4"><div class="text-2xl font-black text-rose-700">{{ $preview['schedule_actions'] }}</div><div class="text-sm text-slate-600">Avisos antigos de escala/troca</div></div>
            <div class="rounded-xl border border-rose-100 bg-rose-50/40 p-4"><div class="text-2xl font-black text-rose-700">{{ $preview['confirmations'] + $preview['additional_ministers'] }}</div><div class="text-sm text-slate-600">Confirmações/participações da escala</div></div>
            <div class="rounded-xl border border-rose-100 bg-rose-50/40 p-4"><div class="text-2xl font-black text-rose-700">{{ $preview['special_events'] }}</div><div class="text-sm text-slate-600">Eventos especiais (se selecionado)</div></div>
            <div class="rounded-xl border border-rose-100 bg-rose-50/40 p-4"><div class="text-2xl font-black text-rose-700">{{ $preview['special_event_actions'] + $preview['special_event_whatsapp_logs'] }}</div><div class="text-sm text-slate-600">Avisos/logs de eventos especiais</div></div>
        </div>
        <div class="mt-4 rounded-xl bg-indigo-50 p-4 text-sm text-indigo-900">
            As ocorrências recorrentes materializadas serão <strong>removidas e permanecerão zeradas</strong> após a limpeza. As regras atuais dos cultos continuam preservadas. Quando você gerar uma nova escala ou usar <strong>Cultos &gt; Gerar recorrências</strong>, somente as ocorrências vigentes serão criadas novamente.
        </div>
    </section>
</div>

<section class="sgml-card mt-5 border-2 border-rose-300 p-5 sm:p-6">
    <h2 class="text-lg font-black text-rose-800">Executar limpeza</h2>
    <p class="mt-1 text-sm text-slate-600">Antes de apagar qualquer registro, o SGML gera automaticamente um backup PostgreSQL validado. A operação não pode ser executada sem a senha atual do Administrador.</p>

    <form method="POST" action="{{ route('admin.operational-cleanup.destroy') }}" class="mt-5 grid gap-4" data-confirm-title="Limpar dados de teste?" data-confirm-message="Um backup será criado e os dados operacionais de escala serão removidos. Cadastros, biblioteca, ensaios e configurações dos cultos serão preservados." data-confirm-danger="1" data-confirm-label="Criar backup e limpar">
        @csrf
        @method('DELETE')
        <div>
            <label class="sgml-label">Motivo da limpeza</label>
            <textarea class="sgml-input min-h-24" name="reason" required minlength="5" maxlength="500" placeholder="Ex.: remover dados de homologação importados da VM anterior">{{ old('reason') }}</textarea>
            @error('reason')<div class="mt-1 text-sm text-rose-700">{{ $message }}</div>@enderror
        </div>
        <label class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
            <input class="mt-1" type="checkbox" name="remove_manual_services" value="1">
            <span><strong>Também remover {{ $preview['manual_services'] }} culto(s) avulso(s)/manual(is)</strong><br><span class="text-amber-800">Opcional e desmarcado por segurança. Use somente se essas ocorrências avulsas também foram criadas durante seus testes.</span></span>
        </label>

        <label class="flex items-start gap-3 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-950">
            <input class="mt-1" type="checkbox" name="regenerate_recurring_services" value="1">
            <span><strong>Recriar recorrências imediatamente após a limpeza</strong><br><span class="text-sky-800">Opcional e desmarcado por padrão. Para uma limpeza realmente vazia, deixe desmarcado. Ao gerar a próxima escala, o mês selecionado será sincronizado automaticamente pelas regras atuais.</span></span>
        </label>
        <label class="flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-950">
            <input class="mt-1" type="checkbox" name="remove_special_events" value="1" checked>
            <span><strong>Remover também {{ $preview['special_events'] }} Evento(s) Especial(is) de teste</strong><br><span class="text-rose-800">Inclui eventos cancelados/arquivados, respostas, culto vinculado, avisos da Central de Ações e logs de WhatsApp. Desmarque apenas se existir Evento Especial real que precise ser preservado.</span></span>
        </label>
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="sgml-label">Senha atual do Administrador</label>
                <input class="sgml-input" type="password" name="password" required autocomplete="current-password">
                @error('password')<div class="mt-1 text-sm text-rose-700">{{ $message }}</div>@enderror
            </div>
            <div>
                <label class="sgml-label">Digite <strong>LIMPAR TESTES</strong></label>
                <input class="sgml-input" name="confirmation" required autocomplete="off" pattern="LIMPAR TESTES" placeholder="LIMPAR TESTES">
                @error('confirmation')<div class="mt-1 text-sm text-rose-700">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
            <span class="text-xs text-slate-500">Biblioteca, ensaios, cadastros, tons lembrados e regras dos cultos não serão apagados.</span>
            <button class="sgml-btn-danger" data-loading-label="Criando backup e limpando...">Criar backup e limpar dados de teste</button>
        </div>
    </form>
</section>
@endsection
