<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecurringServiceRule extends BaseModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'excluded_weeks_of_month' => 'array',
        ];
    }

    public function type(): BelongsTo { return $this->belongsTo(ServiceType::class, 'service_type_id'); }
    public function congregation(): BelongsTo { return $this->belongsTo(Congregation::class); }
    public function location(): BelongsTo { return $this->belongsTo(Location::class); }
}
