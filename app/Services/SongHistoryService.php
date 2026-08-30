<?php

namespace App\Services;

use App\Models\Song;
use App\Models\SongExecution;

class SongHistoryService
{
    public function stats(Song $song): array
    {
        $base = SongExecution::query()->where('song_id',$song->id)->whereHas('serviceExecution',fn($q)=>$q->where('status','finished'));
        $last = (clone $base)->with('serviceExecution.service')->latest('created_at')->first();
        return [
            'total'=>(clone $base)->count(),
            'last_execution'=>$last?->serviceExecution?->service?->starts_at,
            'last_30_days'=>(clone $base)->where('created_at','>=',now()->subDays(30))->count(),
            'last_90_days'=>(clone $base)->where('created_at','>=',now()->subDays(90))->count(),
        ];
    }
}
