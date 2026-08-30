<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class SongCategory extends BaseModel
{
    use SoftDeletes;
    protected function casts(): array { return ['active'=>'boolean']; }
}
