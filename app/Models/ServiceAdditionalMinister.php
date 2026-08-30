<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceAdditionalMinister extends BaseModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'position' => 'integer'];
    }

    public function service(): BelongsTo { return $this->belongsTo(Service::class); }
    public function minister(): BelongsTo { return $this->belongsTo(Person::class, 'person_id'); }
}
