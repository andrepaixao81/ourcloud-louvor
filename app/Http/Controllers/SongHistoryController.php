<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\Song;
use App\Models\SongExecution;
use App\Services\ServiceHistorySyncService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SongHistoryController extends Controller
{
    public function index(Request $request, ServiceHistorySyncService $historySync)
    {
        // RC46: garante que cultos já encerrados sejam materializados no histórico
        // mesmo se o scheduler tiver atrasado entre execuções.
        $historySync->syncDue(500);
        $from = Carbon::parse($request->input('from', now()->subYear()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->input('to', now()->toDateString()))->endOfDay();
        if ($to->lt($from)) [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];

        $ministerId = $request->input('minister_id');
        $songId = $request->input('song_id');
        $songQuery = trim((string) $request->input('q', ''));

        $executions = SongExecution::query()
            ->with([
                'song.category',
                'performer',
                'serviceExecution.service.assignment.minister',
                'serviceExecution.service.additionalMinisters.minister',
                'serviceExecution.ministers.person',
            ])
            ->whereHas('serviceExecution.service', fn ($q) => $q->whereBetween('starts_at', [$from, $to]))
            ->when($songId, fn ($q) => $q->where('song_id', $songId))
            ->when($songQuery !== '', function ($q) use ($songQuery): void {
                $term = '%'.mb_strtolower($songQuery).'%';
                $q->where(function ($where) use ($term): void {
                    $where->whereRaw("lower(coalesce(song_title_snapshot, '')) like ?", [$term])
                        ->orWhereHas('song', fn ($song) => $song->whereRaw('lower(title) like ?', [$term]));
                });
            })
            ->latest('created_at')
            ->limit(1500)
            ->get()
            ->map(function (SongExecution $execution) {
                $execution->setRelation('resolvedPerformer', $this->resolvePerformer($execution));
                return $execution;
            });

        if ($ministerId) {
            $executions = $executions->filter(fn (SongExecution $execution) => $execution->resolvedPerformer?->id === $ministerId)->values();
        }

        $ministerSongs = $this->ministerSongSummary($executions, $ministerId);
        $songMinisters = $this->songMinisterSummary($executions, $songId, $songQuery);

        return view('songs.history', [
            'from' => $from,
            'to' => $to,
            'ministerId' => $ministerId,
            'songId' => $songId,
            'songQuery' => $songQuery,
            'executions' => $executions->take(200),
            'ministerSongs' => $ministerSongs,
            'songMinisters' => $songMinisters,
            'ministers' => Person::query()
                ->where('status', 'active')
                ->whereHas('ministryRoles', fn ($q) => $q->where('is_minister_role', true)->where('active', true))
                ->orderBy('display_name')
                ->get(),
            'songs' => Song::query()->where('status', 'active')->orderBy('title')->get(['id','title']),
        ]);
    }

    private function resolvePerformer(SongExecution $execution): ?Person
    {
        if ($execution->performer) return $execution->performer;

        $effective = $execution->serviceExecution?->ministers?->pluck('person')->filter()->unique('id')->values() ?? collect();
        if ($effective->count() === 1) return $effective->first();

        $service = $execution->serviceExecution?->service;
        if (! $service) return null;

        $scheduled = collect([$service->assignment?->minister])
            ->filter()
            ->merge($service->additionalMinisters?->pluck('minister')->filter() ?? collect())
            ->unique('id')
            ->values();

        return $scheduled->count() === 1 ? $scheduled->first() : null;
    }

    private function ministerSongSummary(Collection $executions, ?string $ministerId): Collection
    {
        if (! $ministerId) return collect();

        return $executions
            ->groupBy(fn (SongExecution $execution) => $execution->song_id ?: 'snapshot:'.mb_strtolower((string) ($execution->song?->title ?: $execution->song_title_snapshot)))
            ->map(function (Collection $items) {
                $first = $items->first();
                $keys = $items->groupBy(fn ($item) => trim((string) ($item->performed_key ?: 'Não informado')))
                    ->map(fn (Collection $keyItems, string $key) => ['key'=>$key, 'count'=>$keyItems->count()])
                    ->sortByDesc('count')->values();
                return [
                    'title' => $first->song?->title ?: $first->song_title_snapshot ?: 'Louvor não identificado',
                    'category' => $first->song?->category?->name,
                    'count' => $items->count(),
                    'keys' => $keys,
                    'last_at' => $items->map(fn ($item) => $item->serviceExecution?->service?->starts_at)->filter()->sortDesc()->first(),
                ];
            })
            ->sortByDesc('count')->values();
    }

    private function songMinisterSummary(Collection $executions, ?string $songId, string $songQuery): Collection
    {
        if (! $songId && $songQuery === '') return collect();

        return $executions
            ->groupBy(fn (SongExecution $execution) => $execution->resolvedPerformer?->id ?: 'unknown')
            ->map(function (Collection $items) {
                $person = $items->first()->resolvedPerformer;
                $keys = $items->groupBy(fn ($item) => trim((string) ($item->performed_key ?: 'Não informado')))
                    ->map(fn (Collection $keyItems, string $key) => ['key'=>$key, 'count'=>$keyItems->count()])
                    ->sortByDesc('count')->values();
                return [
                    'minister' => $person?->display_name ?: 'Ministro não individualizado no registro histórico',
                    'count' => $items->count(),
                    'keys' => $keys,
                    'last_at' => $items->map(fn ($item) => $item->serviceExecution?->service?->starts_at)->filter()->sortDesc()->first(),
                ];
            })
            ->sortByDesc('count')->values();
    }
}
