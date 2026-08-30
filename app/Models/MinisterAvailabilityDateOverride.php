<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class MinisterAvailabilityDateOverride extends BaseModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['override_date' => 'date'];
    }
}
