<?php

namespace App\Http\Controllers;

use App\Models\RehearsalSongItem;
use App\Models\Repertoire;
use App\Models\RepertoireItem;
use App\Models\Service;
use App\Models\Song;
use App\Services\AuditService;
use App\Services\RepertoireService;
use App\Services\TeamNotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RehearsalController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status', 'practicing');
        if (! in_array($status, ['practicing', 'ready', 'removed', 'all'], true)) $status = 'practicing';

        $items = RehearsalSongItem::query()
            ->with(['song.category', 'song.versions', 'addedBy', 'resolvedBy'])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->orderByRaw("case when status = 'practicing' then 0 else 1 end")
            ->orderBy('priority')
            ->latest('updated_at')
            ->get();

        $services = Service::query()
            ->with([
                'assignment.minister',
                'additionalMinisters.minister',
                'repertoire.openingSong.versions',
                'repertoire.openingSongVersion',
                'repertoire.openingMinister',
                'repertoire.items.song.versions',
                'repertoire.items.songVersion',
                'repertoire.items.minister',
            ])
            ->whereNotIn('status', ['cancelled'])
            ->where('starts_at', '>=', now()->subHours(6))
            ->where('starts_at', '<=', now()->addDays(45))
            ->whereHas('assignment', fn ($q) => $q->where('status', 'published'))
            ->whereHas('repertoire', fn ($q) => $q->where(function ($rep): void {
                $rep->whereNotNull('opening_song_id')->orWhereHas('items');
            }))
            ->orderBy('starts_at')
            ->limit(16)
            ->get();

        $editableItemIds = [];
        $editableOpeningIds = [];
        $user = $request->user();
        $personId = $user->person?->id;
        $manageAll = $user->hasPermission('repertoire.manage_all');
        $manageOwn = $user->hasPermission('repertoire.manage_own');

        foreach ($services as $service) {
            $ministerIds = collect([$service->assignment?->minister_person_id])
                ->merge($service->additionalMinisters->where('status', 'published')->pluck('person_id'))
                ->filter()->unique()->values();

            $openingMinisterId = $service->repertoire?->opening_minister_person_id;
            if ($service->repertoire?->opening_song_id && ($manageAll || ($manageOwn && $personId && (
                $openingMinisterId === $personId || (! $openingMinisterId && $ministerIds->count() === 1 && $ministerIds->first() === $personId)
            )))) {
                $editableOpeningIds[] = $service->repertoire->id;
            }

            foreach ($service->repertoire?->items ?? [] as $item) {
                if ($manageAll || ($manageOwn && $personId && (
                    $item->minister_person_id === $personId || (! $item->minister_person_id && $ministerIds->count() === 1 && $ministerIds->first() === $personId)
                ))) {
                    $editableItemIds[] = $item->id;
                }
            }
        }

        return view('rehearsals.index', [
            'items' => $items,
            'status' => $status,
            'songs' => Song::query()->with(['category','versions'])->where('status', 'active')->orderBy('title')->get(),
            'upcomingServices' => $services,
            'editableItemIds' => $editableItemIds,
            'editableOpeningIds' => $editableOpeningIds,
        ]);
    }

    public function updateRepertoireKey(Request $request, RepertoireItem $item, RepertoireService $repertoires, AuditService $audit)
    {
        $item->loadMissing(['repertoire.service.assignment.minister', 'repertoire.service.additionalMinisters.minister', 'song', 'minister']);
        $repertoire = $item->repertoire;
        abort_unless($repertoire?->service, 404);

        $ministerId = $item->minister_person_id;
        if (! $ministerId) $ministerId = $this->singleMinisterFor($repertoire->service);
        $this->authorizeKeyChange($request, $repertoire->service, $ministerId);

        $data = $request->validate(['planned_key' => ['nullable', 'string', 'max:20']]);
        $before = ['planned_key' => $item->planned_key, 'minister_person_id' => $item->minister_person_id];

        $updated = $repertoires->updateItem(
            $repertoire,
            $item->id,
            $data['planned_key'] ?? null,
            $item->specific_youtube_url,
            $item->notes,
            $ministerId,
            'rehearsal',
        );

        $audit->event('rehearsal.repertoire_key.updated', $updated, null, [
            'before' => $before,
            'after' => ['planned_key' => $updated->planned_key, 'minister_person_id' => $updated->minister_person_id],
        ]);

        return back()->with('status', 'Tom do ensaio salvo. Este passa a ser o tom lembrado para este ministro e louvor.');
    }

    public function updateOpeningKey(Request $request, Repertoire $repertoire, RepertoireService $repertoires, AuditService $audit)
    {
        $repertoire->loadMissing(['service.assignment.minister', 'service.additionalMinisters.minister', 'openingSong', 'openingMinister']);
        abort_unless($repertoire->service && $repertoire->openingSong, 404);

        $ministerId = $repertoire->opening_minister_person_id ?: $this->singleMinisterFor($repertoire->service);
        $this->authorizeKeyChange($request, $repertoire->service, $ministerId);
        $data = $request->validate(['opening_key' => ['nullable', 'string', 'max:20']]);
        $before = ['opening_key' => $repertoire->opening_key, 'opening_minister_person_id' => $repertoire->opening_minister_person_id];

        $repertoires->updateOpening(
            $repertoire,
            $repertoire->opening_song_id,
            $repertoire->opening_song_version_id,
            $data['opening_key'] ?? null,
            $repertoire->opening_youtube_url,
            $repertoire->opening_notes,
            $ministerId,
            'rehearsal',
        );

        $repertoire->refresh();
        $audit->event('rehearsal.opening_key.updated', $repertoire, null, [
            'before' => $before,
            'after' => ['opening_key' => $repertoire->opening_key, 'opening_minister_person_id' => $repertoire->opening_minister_person_id],
        ]);

        return back()->with('status', 'Tom da abertura salvo. O sistema lembrará este tom para o ministro nas próximas vezes.');
    }

    public function store(Request $request, AuditService $audit, TeamNotificationService $teamNotifications)
    {
        $data = $request->validate([
            'song_id' => ['required', 'uuid'],
            'priority' => ['required', 'integer', 'between:1,3'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $song = Song::query()->where('status', 'active')->findOrFail($data['song_id']);

        $existing = RehearsalSongItem::query()->where('song_id', $song->id)->where('status', 'practicing')->first();
        if ($existing) {
            $existing->update(['priority' => $data['priority'], 'notes' => $data['notes'] ?? $existing->notes]);
            return back()->with('status', 'Este louvor já estava em ensaio; prioridade/observação foram atualizadas.');
        }

        $item = RehearsalSongItem::query()->create([
            'organization_id' => $request->user()->organization_id,
            'song_id' => $song->id,
            'status' => 'practicing',
            'priority' => $data['priority'],
            'notes' => $data['notes'] ?? null,
            'added_by' => $request->user()->id,
        ]);
        $audit->event('rehearsal.song.added', $item, null, ['song_id' => $song->id, 'priority' => $data['priority']]);

        $song->loadMissing(['versions', 'category']);
        $version = $song->versions->first(fn ($candidate) => $candidate->is_default && $candidate->active)
            ?? $song->versions->first(fn ($candidate) => $candidate->active && ($candidate->youtube_url || $candidate->original_key))
            ?? $song->versions->first(fn ($candidate) => $candidate->active)
            ?? $song->versions->first();
        $priorityLabel = [1 => 'Alta', 2 => 'Normal', 3 => 'Baixa'][$data['priority']] ?? (string) $data['priority'];
        $summaryLines = [
            'Louvor: '.$song->title,
            'Artista: '.($song->artist_reference ?: ($version?->artist ?: 'Não informado')),
            'Prioridade: '.$priorityLabel,
            'Tom original: '.($version?->original_key ?: 'Não informado'),
        ];
        if (filled($data['notes'] ?? null)) $summaryLines[] = 'Observação: '.trim((string) $data['notes']);
        $summaryLines[] = $version?->youtube_url ? 'Referência: disponível no SGML' : 'Referência: ainda não informada';

        $teamNotifications->announce(
            $request->user()->organization_id,
            'Novo louvor em ensaio: '.$song->title,
            implode("\n", $summaryLines),
            route('rehearsals.index'),
            'rehearsal.song.added:'.$item->id,
            'normal'
        );

        return back()->with('status', 'Louvor adicionado à lista de ensaio. A equipe foi avisada.');
    }

    public function update(Request $request, RehearsalSongItem $item, AuditService $audit)
    {
        $data = $request->validate([
            'priority' => ['required', 'integer', 'between:1,3'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['practicing', 'ready', 'removed'])],
        ]);
        $before = $item->only(['priority','notes','status']);
        $resolving = $data['status'] !== 'practicing';
        $item->update($data + [
            'resolved_by' => $resolving ? $request->user()->id : null,
            'resolved_at' => $resolving ? now() : null,
        ]);
        $audit->event('rehearsal.song.updated', $item, null, ['before' => $before, 'after' => $item->only(['priority','notes','status'])]);
        return back()->with('status', $data['status'] === 'ready' ? 'Louvor marcado como pronto e retirado da lista ativa de ensaio.' : 'Item de ensaio atualizado.');
    }

    public function remove(Request $request, RehearsalSongItem $item, AuditService $audit)
    {
        $item->update(['status' => 'removed', 'resolved_by' => $request->user()->id, 'resolved_at' => now()]);
        $audit->event('rehearsal.song.removed', $item);
        return back()->with('status', 'Louvor removido da lista ativa de ensaio.');
    }

    public function reactivate(Request $request, RehearsalSongItem $item, AuditService $audit)
    {
        $active = RehearsalSongItem::query()->where('song_id', $item->song_id)->where('status', 'practicing')->where('id', '!=', $item->id)->exists();
        if ($active) return back()->withErrors(['rehearsal' => 'Este louvor já possui um item ativo em ensaio.']);
        $item->update(['status' => 'practicing', 'resolved_by' => null, 'resolved_at' => null, 'added_by' => $request->user()->id]);
        $audit->event('rehearsal.song.reactivated', $item);
        return back()->with('status', 'Louvor retornou para a lista de ensaio.');
    }

    private function authorizeKeyChange(Request $request, Service $service, ?string $ministerId): void
    {
        $user = $request->user();
        if ($user->hasPermission('repertoire.manage_all')) return;
        abort_unless($user->hasPermission('repertoire.manage_own'), 403, 'Você pode visualizar os tons, mas somente o ministro deste louvor ou a liderança pode alterá-los.');

        $personId = $user->person?->id;
        abort_unless($personId && $ministerId === $personId, 403, 'Somente o ministro definido para este louvor pode salvar o próprio tom.');
    }

    private function singleMinisterFor(Service $service): ?string
    {
        $service->loadMissing(['assignment', 'additionalMinisters']);
        $ids = collect([$service->assignment?->minister_person_id])
            ->merge($service->additionalMinisters->where('status', 'published')->pluck('person_id'))
            ->filter()->unique()->values();

        return $ids->count() === 1 ? (string) $ids->first() : null;
    }
}
