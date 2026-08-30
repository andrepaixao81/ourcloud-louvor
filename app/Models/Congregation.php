<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Congregation extends BaseModel
{
    use SoftDeletes;

    protected function casts(): array { return ['active' => 'boolean']; }

    public function locations(): HasMany { return $this->hasMany(Location::class); }
    public function people(): HasMany { return $this->hasMany(Person::class); }
    public function services(): HasMany { return $this->hasMany(Service::class); }
}
