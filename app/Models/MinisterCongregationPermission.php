<?php

namespace App\Models;

class MinisterCongregationPermission extends BaseModel
{
    protected function casts(): array { return ['allowed'=>'boolean']; }
}
