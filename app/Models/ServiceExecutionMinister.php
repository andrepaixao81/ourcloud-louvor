<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceExecutionMinister extends BaseModel
{
    use SoftDeletes;
    protected function casts(): array { return ['is_primary'=>'boolean']; }
    public function person(): BelongsTo { return $this->belongsTo(Person::class); }
}
