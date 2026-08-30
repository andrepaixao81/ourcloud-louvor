<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SwapRequest extends BaseModel
{
    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo { return $this->belongsTo(ScheduleAssignment::class, 'schedule_assignment_id'); }
    public function requester(): BelongsTo { return $this->belongsTo(Person::class, 'requester_person_id'); }
    public function targetPerson(): BelongsTo { return $this->belongsTo(Person::class, 'target_person_id'); }
}
