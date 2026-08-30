@extends('layouts.app')
@section('content')
<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-black">Backup e restauração</h1>
        <p class="mt-1 text-sm text-slate-500">Área exclusiva do Administrador para gerar, baixar, importar e restaurar backups completos do PostgreSQL sem precisar operar o Linux manualmente.</p>
    </div>
    <a href="{{ route('admin.index') }}" class="sgml-btn-secondary">Voltar à Administração</a>
</div>

@if(session('success'))
    <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ session('error') }}</div>
@endif

@if($restoreStatus)
    @php
        $restoreState = (string) ($restoreStatus['state'] ?? 'scheduled');
        $restoreClass = $restoreState === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : ($restoreState === 'failed' ? 'border-rose-200 bg-rose-50 text-rose-900' : 'border-amber-200 bg-amber-50 text-amber-950');
    @endphp
    <div class="mb-5 rounded-xl border p-4 text-sm {{ $restoreClass }}">
        <div class="font-black">Status da restauração: {{ $restoreState === 'success' ? 'concluída' : ($restoreState === 'failed' ? 'falhou' : 'em andamento/agendada') }}</div>
        <div class="mt-1">{{ $restoreStatus['message'] ?? 'Acompanhe a disponibilidade do SGML e atualize esta página em alguns instantes.' }}</div>
        @if(!empty($restoreStatus['finished_at']))<div class="mt-1 text-xs opacity-80">Finalizada em {{ $restoreStatus['finished_at'] }}</div>@endif
    </div>
@endif

<div class="grid gap-4 lg:grid-cols-3">
    <div class="sgml-card p-5 lg:col-span-2">
        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <h2 class="text-lg font-black">Gerar backup agora</h2>
                <p class="mt-1 text-sm text-slate-500">Cria um <code>.dump</code> completo, valida com <code>pg_restore</code> e calcula SHA-256 antes de liberar o arquivo.</p>
                <form method="POST" action="{{ route('admin.backups.store') }}" class="mt-4" data-confirm-message="Gerar agora um backup completo do banco PostgreSQL?">
                    @csrf
                    <button class="sgml-btn-primary" @if(!$diagnostics['path_writable'] || !$diagnostics['pg_dump_available'] || !$diagnostics['pg_restore_available']) disabled @endif>Gerar backup do banco</button>
                </form>
            </div>
            <div class="border-t border-slate-200 pt-5 md:border-l md:border-t-0 md:pl-5 md:pt-0">
                <h2 class="text-lg font-black">Trazer backup de outra VM</h2>
                <p class="mt-1 text-sm text-slate-500">Envie pelo navegador um arquivo <code>.dump</code> gerado pelo SGML. O arquivo será validado antes de aparecer na lista.</p>
                <form method="POST" action="{{ route('admin.backups.import') }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                    @csrf
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <input
                            id="backup_file"
                            type="file"
                            name="backup_file"
                            accept=".dump"
                            required
                            style="position:absolute;width:1px;height:1px;opacity:0;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;"
                            onchange="document.getElementById('backup_file_name').textContent = (this.files && this.files.length) ? this.files[0].name : 'Nenhum arquivo selecionado'; document.getElementById('backup_import_submit').disabled = !(this.files && this.files.length);"
                        >
                        <div class="flex flex-wrap items-center gap-3">
                            <label for="backup_file" class="sgml-btn-secondary" style="cursor:pointer;">Selecionar arquivo .dump</label>
                            <span id="backup_file_name" class="text-sm font-semibold text-slate-700">Nenhum arquivo selecionado</span>
                        </div>
                        <p class="mt-2 text-xs text-slate-500">Clique em <strong>Selecionar arquivo .dump</strong> para abrir o seletor do navegador. Somente arquivos de backup PostgreSQL <code>.dump</code> são aceitos.</p>
                    </div>
                    <div class="text-xs text-slate-500">Limite configurado: {{ $diagnostics['upload_max_mb'] }} MB.</div>
                    <button id="backup_import_submit" class="sgml-btn-secondary" disabled>Importar e validar backup</button>
                </form>
            </div>
        </div>

        <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
            <strong>Migração para outra VM:</strong> além do banco, preserve a mesma <code>APP_KEY</code> da instalação de origem. Ela protege dados criptografados e não é colocada dentro do backup por segurança. O SGML bloqueia restaurações quando consegue detectar que a chave atual é diferente da chave usada para gerar o backup.
        </div>
    </div>

    <div class="sgml-card p-5">
        <h2 class="text-base font-black">Diagnóstico</h2>
        <dl class="mt-3 space-y-2 text-sm">
            <div class="flex justify-between gap-3"><dt class="text-slate-500">PostgreSQL</dt><dd class="font-semibold">{{ $diagnostics['server_version'] }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">Banco</dt><dd class="font-semibold">{{ $diagnostics['database'] }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">pg_dump</dt><dd class="font-semibold {{ $diagnostics['pg_dump_available'] ? 'text-emerald-700' : 'text-rose-700' }}">{{ $diagnostics['pg_dump_available'] ? 'Disponível' : 'Ausente' }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">pg_restore</dt><dd class="font-semibold {{ $diagnostics['pg_restore_available'] ? 'text-emerald-700' : 'text-rose-700' }}">{{ $diagnostics['pg_restore_available'] ? 'Disponível' : 'Ausente' }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">Restauração web</dt><dd class="font-semibold {{ $diagnostics['restore_dispatcher_available'] && $diagnostics['restore_request_writable'] ? 'text-emerald-700' : 'text-rose-700' }}">{{ $diagnostics['restore_dispatcher_available'] && $diagnostics['restore_request_writable'] ? 'Pronta' : 'Requer hotfix' }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">Diretório</dt><dd class="font-semibold {{ $diagnostics['path_writable'] ? 'text-emerald-700' : 'text-rose-700' }}">{{ $diagnostics['path_writable'] ? 'Gravável' : 'Não gravável' }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">APP_KEY</dt><dd class="font-mono text-xs">{{ $diagnostics['app_key_fingerprint'] }}</dd></div>
        </dl>
    </div>
</div>

@if($restoreCandidate)
    <div id="restore-confirmation" class="mt-6 rounded-2xl border-2 border-rose-300 bg-rose-50 p-5">
        <h2 class="text-xl font-black text-rose-950">Restaurar backup selecionado</h2>
        <p class="mt-2 text-sm text-rose-900"><strong>Esta operação substitui o banco atual.</strong> O SGML ficará temporariamente em manutenção, criará um backup pré-restauração, restaurará os dados e reaplicará migrations da versão instalada.</p>
        <div class="mt-4 grid gap-3 rounded-xl bg-white p-4 text-sm md:grid-cols-2">
            <div><span class="text-slate-500">Arquivo</span><div class="font-mono text-xs font-bold">{{ $restoreCandidate['filename'] }}</div></div>
            <div><span class="text-slate-500">SHA-256</span><div class="font-mono text-xs font-bold">{{ substr($restoreCandidate['sha256'], 0, 24) }}…</div></div>
            <div><span class="text-slate-500">APP_KEY do backup</span><div class="font-mono text-xs font-bold">{{ $restoreCandidate['source_app_key_fingerprint'] }}</div></div>
            <div><span class="text-slate-500">APP_KEY atual</span><div class="font-mono text-xs font-bold">{{ $restoreCandidate['current_app_key_fingerprint'] }}</div></div>
        </div>
        @if($restoreCandidate['app_key_known'] && !$restoreCandidate['app_key_matches'])
            <div class="mt-4 rounded-xl border border-rose-300 bg-white p-4 text-sm font-semibold text-rose-800">A APP_KEY atual é diferente da chave registrada no backup. Configure primeiro a APP_KEY da VM de origem. A restauração será bloqueada.</div>
        @else
            @if(!$restoreCandidate['app_key_known'])
                <div class="mt-4 rounded-xl border border-amber-300 bg-amber-100 p-4 text-sm text-amber-950">O backup foi importado e não possui fingerprint da APP_KEY de origem. Antes de continuar em outra VM, confirme que você copiou a APP_KEY da instalação antiga.</div>
            @endif
            <form method="POST" action="{{ route('admin.backups.restore', ['filename' => $restoreCandidate['filename']]) }}" class="mt-4 grid gap-4 md:grid-cols-2">
                @csrf
                <div class="md:col-span-2"><label class="sgml-label">Motivo da restauração</label><input class="sgml-input" name="reason" required minlength="5" maxlength="500" placeholder="Ex.: migração para nova VM ou recuperação após falha"></div>
                <div><label class="sgml-label">Sua senha atual de Administrador</label><input class="sgml-input" type="password" name="password" autocomplete="current-password" required></div>
                <div><label class="sgml-label">Digite RESTAURAR</label><input class="sgml-input" name="confirmation" required pattern="RESTAURAR" placeholder="RESTAURAR"></div>
                <label class="md:col-span-2 flex items-start gap-2 rounded-xl border border-rose-200 bg-white p-3 text-sm text-slate-700"><input type="checkbox" name="app_key_ack" value="1" required class="mt-1"><span>Confirmo que entendi que o banco atual será substituído e que, em migração entre VMs, a APP_KEY da instalação de origem deve estar configurada antes da restauração.</span></label>
                <div class="md:col-span-2 flex flex-wrap gap-2"><button class="sgml-btn-danger">Restaurar este backup</button><a class="sgml-btn-secondary" href="{{ route('admin.backups.index') }}">Cancelar</a></div>
            </form>
        @endif
    </div>
@endif

<div class="mt-6 sgml-card overflow-hidden">
    <div class="border-b border-slate-200 p-5">
        <h2 class="text-lg font-black">Backups disponíveis</h2>
        <p class="mt-1 text-sm text-slate-500">Os arquivos ficam fora do webroot. Um backup pode ser baixado, restaurado ou excluído somente por Administrador.</p>
    </div>

    @if(empty($backups))
        <div class="p-8 text-center text-sm text-slate-500">Nenhum backup administrativo disponível.</div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Criado</th><th class="px-5 py-3">Origem</th><th class="px-5 py-3">Versão</th><th class="px-5 py-3">Tamanho</th><th class="px-5 py-3">SHA-256</th><th class="px-5 py-3 text-right">Ações</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @foreach($backups as $backup)
                    <tr>
                        <td class="px-5 py-4 whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($backup['created_at'])->timezone(config('app.timezone'))->format('d/m/Y H:i:s') }}</td>
                        <td class="px-5 py-4">{{ ($backup['source'] ?? 'generated') === 'uploaded' ? 'Importado' : 'Gerado no SGML' }}</td>
                        <td class="px-5 py-4 whitespace-nowrap">{{ $backup['app_version'] ?? '-' }}</td>
                        <td class="px-5 py-4 whitespace-nowrap">{{ number_format(($backup['size_bytes'] ?? 0) / 1024 / 1024, 2, ',', '.') }} MB</td>
                        <td class="px-5 py-4"><code class="text-xs">{{ substr((string) ($backup['sha256'] ?? ''), 0, 16) }}…</code></td>
                        <td class="px-5 py-4"><div class="flex flex-wrap justify-end gap-2">
                            <a class="sgml-btn-secondary" href="{{ route('admin.backups.download', ['filename' => $backup['filename']]) }}">Baixar</a>
                            <a class="sgml-btn-primary" href="{{ route('admin.backups.index', ['restore' => $backup['filename']]) }}#restore-confirmation">Restaurar</a>
                            <form method="POST" action="{{ route('admin.backups.destroy', ['filename' => $backup['filename']]) }}" data-confirm-message="Excluir definitivamente este arquivo de backup do servidor?">@csrf @method('DELETE')<input type="hidden" name="reason" value="Exclusão manual pelo Administrador"><button class="sgml-btn-danger">Excluir</button></form>
                        </div></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="mt-6 sgml-card p-5">
    <h2 class="text-lg font-black">Restaurar em outra VM sem Linux avançado</h2>
    <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm text-slate-600">
        <li>Na VM antiga, gere o backup e baixe o arquivo <code>.dump</code>.</li>
        <li>Guarde também a <code>APP_KEY</code> da instalação antiga em local seguro.</li>
        <li>Instale o SGML na nova VM e configure essa mesma APP_KEY.</li>
        <li>Entre como Administrador, abra esta tela e use <strong>Trazer backup de outra VM</strong>.</li>
        <li>Depois da validação, clique em <strong>Restaurar</strong> e confirme com sua senha.</li>
    </ol>
    <p class="mt-3 text-xs text-slate-500">O utilitário de linha de comando continua disponível em <code>deploy/restore-database-backup.sh</code> para administradores técnicos, mas não é mais obrigatório no fluxo normal.</p>
</div>
@endsection
