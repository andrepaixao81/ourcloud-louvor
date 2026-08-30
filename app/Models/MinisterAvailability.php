<?php

namespace App\Models;

class MinisterAvailability extends BaseModel
{
    protected function casts(): array { return ['active'=>'boolean']; }
}
