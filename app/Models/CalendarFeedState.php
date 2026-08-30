<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CalendarFeedState extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'event_starts_at' => 'datetime',
            'last_published_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'sequence' => 'integer',
        ];
    }
}
