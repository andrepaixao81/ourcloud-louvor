@extends('layouts.app')
@section('content')
<?php
    $statusNames = [
        'requested' => 'Aguardando resposta',
        'accepted' => 'Aceita e aplicada',
        'rejected' => 'Recusada',
        'approved' => 'Aplicada pela administração',
        'cancelled' => 'Cancelada',
    ];
    $canForceSwap = auth()->check() && auth()->user()->hasPermission('schedule.force_swap');
    $managementRowsSafe = $managementRows ?? collect();
?>

<div class="mb-6">
    <h1 class="text-2xl font-black">Trocas</h1>
    <p class="text-sm text-slate-500">Ministros podem solicitar substituição entre si. Liderança e Administração também podem alterar diretamente uma escala publicada futura.</p>
</div>

<?php if (($pendingSwapCount ?? 0) > 0): ?>
<div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
    <div><strong>Alerta de troca:</strong> {{ $canForceSwap ? 'há' : 'você possui' }} {{ $pendingSwapCount }} {{ $pendingSwapCount === 1 ? 'solicitação aguardando atendimento' : 'solicitações aguardando atendimento' }}.</div>
    <span class="sgml-badge bg-amber-100 text-amber-800">PENDENTE</span>
</div>
<?php endif; ?>

<?php if ($canForceSwap): ?>
<section class="mb-8 sgml-card p-5">
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-lg font-black">Alterar ministro diretamente</h2>
            <p class="text-sm text-slate-500">Escolha um culto publicado, informe o motivo e substitua o ministro sem depender de uma solicitação prévia. Outro culto no mesmo dia pode ser aceito como exceção; conflito de horário sobreposto e indisponibilidade real continuam bloqueando.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a class="sgml-btn-secondary" href="{{ route('swaps.index', ['month' => $managementMonthDate->copy()->subMonth()->format('Y-m')]) }}">←</a>
            <x-month-year-picker :month="$managementMonthDate" route-name="swaps.index" id="swaps-month-picker" />
            <a class="sgml-btn-secondary" href="{{ route('swaps.index', ['month' => $managementMonthDate->copy()->addMonth()->format('Y-m')]) }}">→</a>
            <a class="text-sm font-semibold text-indigo-600" href="{{ route('swaps.index', ['month' => now()->format('Y-m')]) }}">Hoje</a>
        </div>
    </div>

    <?php if ($errors->has('target_person_id')): ?>
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">{{ $errors->first('target_person_id') }}</div>
    <?php endif; ?>

    <div class="space-y-3">
        <?php foreach ($managementRowsSafe as $managementRow): ?>
            <?php
                $directTargets = $managementRow['targets'] ?? collect();
                $directEligibleCount = (int) ($managementRow['eligible_count'] ?? 0);
                $assignmentId = (string) ($managementRow['assignment_id'] ?? '');
            ?>
            <div class="rounded-xl border border-slate-200 p-4">
                <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <div class="font-bold">{{ $managementRow['service_name'] ?? 'Culto' }}</div>
                        <div class="text-sm text-slate-500">{{ $managementRow['starts_at'] ?? 'data não definida' }} · atual: <strong>{{ $managementRow['current_minister'] ?? 'não definido' }}</strong></div>
                    </div>
                </div>

                <form method="POST" action="{{ route('swaps.force-replace', ['assignment' => $assignmentId]) }}" class="grid gap-2 lg:grid-cols-[minmax(220px,1fr)_minmax(260px,2fr)_auto]">
                    @csrf
                    <select class="sgml-input" name="target_person_id" required {{ $directTargets->isEmpty() ? 'disabled' : '' }}>
                        <option value="">Novo ministro...</option>
                        <?php foreach ($directTargets as $targetOption): ?>
                            <?php
                                $target = $targetOption['person'];
                                $targetSelectable = (bool) ($targetOption['selectable'] ?? false);
                                $blockingReasons = array_values($targetOption['blocking_reasons'] ?? []);
                                $warnings = array_values($targetOption['warnings'] ?? []);
                                $targetSuffix = '';
                                if (! $targetSelectable) {
                                    $targetSuffix = ' · indisponível: '.implode('; ', $blockingReasons);
                                } elseif ($warnings !== []) {
                                    $targetSuffix = ' · atenção: '.implode('; ', $warnings);
                                }
                            ?>
                            <option value="{{ $target->id }}" {{ $targetSelectable ? '' : 'disabled' }}>{{ $target->display_name }}{{ $targetSuffix }}</option>
                        <?php endforeach; ?>
                    </select>
                    <input class="sgml-input" name="reason" required minlength="3" maxlength="2000" placeholder="Motivo da alteração">
                    <button class="sgml-btn-primary" {{ $directEligibleCount === 0 ? 'disabled' : '' }}>Alterar ministro</button>
                </form>

                <?php if ($directTargets->isEmpty()): ?>
                    <p class="mt-2 text-sm text-amber-700">Não há outro ministro ativo cadastrado.</p>
                <?php elseif ($directEligibleCount === 0): ?>
                    <p class="mt-2 text-sm text-amber-700">Todos os outros ministros estão visíveis na lista, mas possuem indisponibilidade ou conflito de horário que impede a troca.</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($managementRowsSafe->isEmpty()): ?>
        <div class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">Nenhuma escala publicada futura encontrada em {{ $managementMonth }}.</div>
    <?php endif; ?>
</section>
<?php endif; ?>

<div class="space-y-4">
<?php if ($swaps->count() > 0): ?>
    <?php foreach ($swaps as $swap): ?>
        <?php
            $targets = $eligibleTargetsBySwap[$swap->id] ?? collect();
            $eligibleTargetCount = $targets->where('selectable', true)->count();
        ?>
        <div class="sgml-card p-5">
            <div class="flex flex-wrap justify-between gap-3">
                <div>
                    <div class="font-bold">{{ $swap->assignment?->service?->name }}</div>
                    <?php if ($swap->assignment?->service?->starts_at): ?>
                        <div class="text-sm text-slate-500">{{ $swap->assignment->service->starts_at->format('d/m/Y H:i') }}</div>
                    <?php endif; ?>
                    <div class="mt-1 text-sm text-slate-500">Solicitante: {{ $swap->requester?->display_name }} · Substituto: {{ $swap->targetPerson?->display_name ?? 'aguardando indicação' }}</div>
                    <?php if ($swap->reason): ?>
                        <p class="mt-2 text-sm">{{ $swap->reason }}</p>
                    <?php endif; ?>
                </div>
                <div class="flex items-center gap-2">
                    <?php if ($swap->status === 'requested'): ?><span class="sgml-badge bg-amber-50 text-amber-700">NOVA SOLICITAÇÃO</span><?php endif; ?>
                    <span class="sgml-badge bg-slate-100 text-slate-700">{{ $statusNames[$swap->status] ?? strtoupper($swap->status) }}</span>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <?php if (auth()->user()->person?->id === $swap->target_person_id && $swap->status === 'requested'): ?>
                    <form method="POST" action="{{ route('swaps.accept', $swap) }}">
                        @csrf
                        <button class="sgml-btn-primary">Aceitar e assumir culto</button>
                    </form>
                    <form method="POST" action="{{ route('swaps.reject', $swap) }}">
                        @csrf
                        <button class="sgml-btn-danger">Recusar</button>
                    </form>
                <?php endif; ?>

                <?php if ($canForceSwap): ?>
                    <?php if ($swap->status === 'requested' && ! $swap->target_person_id): ?>
                        <form method="POST" action="{{ route('swaps.target', $swap) }}" class="flex flex-wrap gap-2">
                            @csrf
                            <select class="sgml-input min-w-64" name="target_person_id" required>
                                <option value="">Direcionar para...</option>
                                <?php foreach ($targets as $targetOption): ?>
                                    <?php
                                        $target = $targetOption['person'];
                                        $targetSelectable = (bool) ($targetOption['selectable'] ?? false);
                                        $blockingReasons = array_values($targetOption['blocking_reasons'] ?? []);
                                        $warnings = array_values($targetOption['warnings'] ?? []);
                                        $targetSuffix = '';
                                        if (! $target->user || ! $target->user->active) {
                                            $targetSuffix .= ' · sem acesso ao sistema';
                                        }
                                        if (! $targetSelectable) {
                                            $targetSuffix .= ' · indisponível: '.implode('; ', $blockingReasons);
                                        } elseif ($warnings !== []) {
                                            $targetSuffix .= ' · atenção: '.implode('; ', $warnings);
                                        }
                                    ?>
                                    <option value="{{ $target->id }}" {{ $targetSelectable ? '' : 'disabled' }}>{{ $target->display_name }}{{ $targetSuffix }}</option>
                                <?php endforeach; ?>
                            </select>
                            <button class="sgml-btn-secondary" {{ $eligibleTargetCount === 0 ? 'disabled' : '' }}>Enviar para aceite</button>
                        </form>

                        <?php if ($targets->isEmpty()): ?>
                            <span class="text-sm text-slate-500">Não há outro ministro ativo cadastrado.</span>
                        <?php elseif ($eligibleTargetCount === 0): ?>
                            <span class="text-sm text-slate-500">Os demais ministros estão visíveis na lista, mas todos possuem indisponibilidade ou conflito de horário que impede a troca.</span>
                        <?php endif; ?>
                    <?php elseif ($swap->status === 'requested'): ?>
                        <form method="POST" action="{{ route('swaps.approve', $swap) }}" data-confirm-message="Aplicar esta troca administrativamente sem aguardar nova confirmação?">
                            @csrf
                            <button class="sgml-btn-secondary">Aplicar administrativamente</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="sgml-card p-10 text-center text-slate-500">Nenhuma solicitação de troca.</div>
<?php endif; ?>
</div>

<div class="mt-4">{{ $swaps->links() }}</div>
@endsection
