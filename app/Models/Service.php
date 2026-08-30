<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends BaseModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'minister_slots' => 'integer',
        ];
    }

    public function type(): BelongsTo { return $this->belongsTo(ServiceType::class, 'service_type_id')->withTrashed(); }
    public function congregation(): BelongsTo { return $this->belongsTo(Congregation::class)->withTrashed(); }
    public function location(): BelongsTo { return $this->belongsTo(Location::class)->withTrashed(); }
    public function recurringRule(): BelongsTo { return $this->belongsTo(RecurringServiceRule::class, 'recurring_rule_id')->withTrashed(); }
    public function specialEvent(): BelongsTo { return $this->belongsTo(SpecialEvent::class); }
    public function assignment(): HasOne { return $this->hasOne(ScheduleAssignment::class); }
    public function additionalMinisters(): HasMany { return $this->hasMany(ServiceAdditionalMinister::class)->orderBy('position'); }
    public function repertoire(): HasOne { return $this->hasOne(Repertoire::class); }
    public function execution(): HasOne { return $this->hasOne(ServiceExecution::class); }
}
