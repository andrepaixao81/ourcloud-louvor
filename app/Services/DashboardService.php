<?php

namespace App\Services;

use App\Models\ActionItem;
use App\Models\ScheduleAssignment;
use App\Models\Service;
use App\Models\Song;
use App\Models\SongExecution;
use App\Models\SwapRequest;
use Carbon\Carbon;

class DashboardService
{
    public function summary(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from ??= now()->startOfYear()->startOfDay();
        $to ??= now()->endOfYear()->endOfDay();
        if ($to->lt($from)) [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];

        $services = Service::query()
            ->with(['assignment.minister','additionalMinisters.minister','execution.ministers.person','execution.songs.song'])
            ->whereBetween('starts_at', [$from, $to])
            ->where('status', '!=', 'cancelled')
            ->orderBy('starts_at')->get();
        $completed = $services->filter(fn ($service) => $service->status === 'completed' || $service->execution?->status === 'finished');

        $ministerStats = [];
        $slotStats = [];
        $weekday = [0=>'Domingo',1=>'Segunda',2=>'Terça',3=>'Quarta',4=>'Quinta',5=>'Sexta',6=>'Sábado'];
        foreach ($completed as $service) {
            $effective = $service->execution?->ministers ?? collect();
            $people = $effective->isNotEmpty()
                ? $effective->pluck('person')->filter()
                : collect([$service->assignment?->minister])->filter()->merge($service->additionalMinisters->pluck('minister')->filter());
            foreach ($people->unique('id') as $person) {
                $ministerStats[$person->id] ??= ['name'=>$person->display_name,'count'=>0];
                $ministerStats[$person->id]['count']++;
            }
        }

        // A faixa dia/horário é planejamento operacional, não apenas histórico.
        // Assim aparece tão logo os cultos sejam criados/gerados, mesmo antes da realização.
        foreach ($services as $service) {
            $slotKey = $service->starts_at->dayOfWeek.'|'.$service->starts_at->format('H:i');
            $slotStats[$slotKey] ??= [
                'label'=>($weekday[$service->starts_at->dayOfWeek] ?? '').' '.$service->starts_at->format('H:i'),
                'count'=>0,
                'completed'=>0,
                'names'=>[],
            ];
            $slotStats[$slotKey]['count']++;
            if ($service->status === 'completed' || $service->execution?->status === 'finished') $slotStats[$slotKey]['completed']++;
            $slotStats[$slotKey]['names'][$service->name] = ($slotStats[$slotKey]['names'][$service->name] ?? 0) + 1;
        }

        $songExecutions = SongExecution::query()
            ->with(['song','performer','serviceExecution.service.assignment.minister','serviceExecution.service.additionalMinisters.minister','serviceExecution.ministers.person'])
            ->whereHas('serviceExecution.service', fn ($q) => $q->whereBetween('starts_at', [$from,$to]))
            ->get();
        $songStats = [];
        foreach ($songExecutions as $execution) {
            $title = $execution->song?->title ?: $execution->song_title_snapshot ?: 'Louvor não identificado';
            $key = $execution->song_id ?: 'snapshot:'.mb_strtolower($title);
            $songStats[$key] ??= ['title'=>$title,'count'=>0,'ministers'=>[],'keys'=>[]];
            $songStats[$key]['count']++;
            $tone = trim((string) ($execution->performed_key ?: 'Não informado'));
            $songStats[$key]['keys'][$tone] = ($songStats[$key]['keys'][$tone] ?? 0) + 1;
            $service = $execution->serviceExecution?->service;
            if ($service) {
                $person = $execution->performer;
                if (! $person) {
                    $effective = $execution->serviceExecution?->ministers?->pluck('person')->filter()->unique('id')->values() ?? collect();
                    if ($effective->count() === 1) $person = $effective->first();
                }
                if (! $person) {
                    $scheduled = collect([$service->assignment?->minister])->filter()->merge($service->additionalMinisters->pluck('minister')->filter())->unique('id')->values();
                    if ($scheduled->count() === 1) $person = $scheduled->first();
                }
                $ministerName = $person?->display_name ?: 'Não individualizado';
                $songStats[$key]['ministers'][$ministerName] = ($songStats[$key]['ministers'][$ministerName] ?? 0) + 1;
            }
        }

        $publishedAssignments = ScheduleAssignment::query()->where('status','published')->whereHas('service', fn ($q)=>$q->whereBetween('starts_at',[$from,$to]))->count();
        $swaps = SwapRequest::query()->whereHas('assignment.service', fn($q)=>$q->whereBetween('starts_at',[$from,$to]))->count();

        $topMinisters = collect($ministerStats)->sortByDesc('count')->values()->take(10);
        $topSongs = collect($songStats)->sortByDesc('count')->values()->take(10)->map(function ($item) {
            arsort($item['ministers']);
            arsort($item['keys']);
            $item['minister_names'] = collect($item['ministers'])->map(fn ($count, $name) => $name.' ('.$count.'x)')->implode(', ');
            $item['tone_summary'] = collect($item['keys'])->map(fn ($count, $key) => $key.' ('.$count.'x)')->implode(', ');
            unset($item['ministers'], $item['keys']);
            return $item;
        });
        $serviceSlots = collect($slotStats)->sortByDesc('count')->values()->take(10)->map(function ($item) {
            arsort($item['names']); $item['common_name'] = array_key_first($item['names']) ?: ''; unset($item['names']); return $item;
        });

        $upcoming = Service::query()
            ->with(['assignment.minister','additionalMinisters.minister','repertoire.items','repertoire.openingSong'])
            ->where('starts_at','>=',now())
            ->where('status','!=','cancelled')
            ->orderBy('starts_at')
            ->limit(12)
            ->get();
        $operationalWindow = $upcoming->filter(fn ($service) => $service->starts_at->lte(now()->addDays(45)));

        return [
            'from'=>$from,'to'=>$to,
            'services_period'=>$services->count(),
            'completed_services'=>$completed->count(),
            'ministerings_count'=>collect($ministerStats)->sum('count'),
            'songs_performed'=>$songExecutions->count(),
            'songs_active'=>Song::query()->where('status','active')->count(),
            'avg_songs_per_service'=>$completed->count() ? round($songExecutions->count()/$completed->count(),1) : 0,
            'published_assignments'=>$publishedAssignments,
            'swaps_period'=>$swaps,
            'pending_actions'=>auth()->check()?ActionItem::query()->where('user_id',auth()->id())->where('status','pending')->count():0,
            'pending_swaps'=>SwapRequest::query()->where('status','requested')->count(),
            'upcoming_without_minister'=>$operationalWindow->filter(fn ($service) => ! $service->assignment?->minister)->count(),
            'upcoming_without_repertoire'=>$operationalWindow->filter(fn ($service) => ! $service->repertoire?->openingSong && ($service->repertoire?->items?->isEmpty() ?? true))->count(),
            'top_ministers'=>$topMinisters,
            'top_songs'=>$topSongs,
            'service_slots'=>$serviceSlots,
            'upcoming'=>$upcoming->take(6)->values(),
        ];
    }
}
