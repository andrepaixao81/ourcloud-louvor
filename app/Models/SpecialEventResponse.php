<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpecialEventResponse extends BaseModel
{
    protected function casts(): array
    {
        return ['responded_at' => 'datetime'];
    }

    public function event(): BelongsTo { return $this->belongsTo(SpecialEvent::class, 'special_event_id'); }
    public function person(): BelongsTo { return $this->belongsTo(Person::class); }
    public function respondedBy(): BelongsTo { return $this->belongsTo(User::class, 'responded_by'); }
}
