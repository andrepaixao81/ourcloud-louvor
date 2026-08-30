<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MinisterServicePermission extends BaseModel
{
    protected function casts(): array
    {
        return ['allowed' => 'boolean'];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }
}
