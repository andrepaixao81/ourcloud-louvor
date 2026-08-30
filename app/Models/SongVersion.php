<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class SongVersion extends BaseModel
{
    use SoftDeletes;
    protected function casts(): array { return ['is_default'=>'boolean','active'=>'boolean']; }
}
