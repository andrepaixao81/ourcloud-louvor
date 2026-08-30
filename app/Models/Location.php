<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Location extends BaseModel
{
    use SoftDeletes;

    protected function casts(): array { return ['active' => 'boolean']; }

    public function congregation(): BelongsTo { return $this->belongsTo(Congregation::class)->withTrashed(); }
    public function services(): HasMany { return $this->hasMany(Service::class); }
}
