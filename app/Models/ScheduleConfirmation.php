<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleConfirmation extends BaseModel
{
    protected function casts(): array { return ['responded_at'=>'datetime']; }
    public function scheduleAssignment(): BelongsTo { return $this->belongsTo(ScheduleAssignment::class); }
}
