<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScheduleAssignment extends BaseModel
{
    use SoftDeletes;
    protected function casts(): array { return ['score'=>'decimal:2','explanation'=>'array','published_at'=>'datetime']; }
    public function service(): BelongsTo { return $this->belongsTo(Service::class); }
    public function minister(): BelongsTo { return $this->belongsTo(Person::class, 'minister_person_id'); }
    public function confirmation(): HasOne { return $this->hasOne(ScheduleConfirmation::class); }
}
