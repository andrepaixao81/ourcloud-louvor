<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class SpecialEvent extends BaseModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function service(): HasOne { return $this->hasOne(Service::class); }
    public function responses(): HasMany { return $this->hasMany(SpecialEventResponse::class); }
}
