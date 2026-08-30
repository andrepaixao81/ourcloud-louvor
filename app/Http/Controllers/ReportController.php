<?php

namespace App\Http\Controllers;

use App\Models\ScheduleAssignment;
use App\Models\ServiceParticipant;
use App\Models\Song;
use App\Models\SongExecution;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        [$from, $to] = $this->period($request);
        $servicePeriod = $this->servicePeriodFilter($from, $to);

        $ministerDistribution = ScheduleAssignment::query()
            ->where('status', 'published')
            ->whereHas('service', $servicePeriod)
            ->with('minister')
            ->get()
            ->groupBy('minister_person_id')
            ->map(fn ($items) => [
                'name' => $items->first()->minister?->display_name,
                'count' => $items->count(),
            ])
            ->sortByDesc('count')
            ->values();

        $songs = SongExecution::query()
            ->select('song_id', DB::raw('count(*) as total'))
            ->whereNotNull('song_id')
            ->whereHas('serviceExecution.service', $servicePeriod)
            ->groupBy('song_id')
            ->orderByDesc('total')
            ->limit(50)
            ->with('song')
            ->get();

        $participations = ServiceParticipant::query()
            ->select('person_id', DB::raw('count(*) as total'))
            ->whereHas('serviceExecution.service', $servicePeriod)
            ->groupBy('person_id')
            ->orderByDesc('total')
            ->limit(50)
            ->with('person')
            ->get();

        return view('reports.index', compact(
            'ministerDistribution',
            'songs',
            'participations',
            'from',
            'to'
        ));
    }

    public function song(Request $request, string $song)
    {
        [$from, $to] = $this->period($request);
        $servicePeriod = $this->servicePeriodFilter($from, $to);
        $songModel = Song::query()->withTrashed()->findOrFail($song);

        $executions = SongExecution::query()
            ->where('song_id', $songModel->id)
            ->whereHas('serviceExecution.service', $servicePeriod)
            ->with([
                'performer',
                'serviceExecution.service.type',
                'serviceExecution.service.location',
                'serviceExecution.service.assignment.minister',
                'serviceExecution.service.additionalMinisters.minister',
            ])
            ->orderByDesc('started_at')
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        return view('reports.song', compact('songModel', 'executions', 'from', 'to'));
    }

    private function period(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $from = filled($data['from'] ?? null) ? Carbon::parse($data['from'])->startOfDay() : null;
        $to = filled($data['to'] ?? null) ? Carbon::parse($data['to'])->endOfDay() : null;

        if ($from && $to && $from->gt($to)) {
            throw ValidationException::withMessages([
                'to' => 'A data final deve ser igual ou posterior à data inicial.',
            ]);
        }

        return [$from, $to];
    }

    private function servicePeriodFilter(?Carbon $from, ?Carbon $to): callable
    {
        return static function (Builder $query) use ($from, $to): void {
            if ($from) {
                $query->where('starts_at', '>=', $from);
            }
            if ($to) {
                $query->where('starts_at', '<=', $to);
            }
        };
    }
}
