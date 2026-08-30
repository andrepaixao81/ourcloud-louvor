<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class CalendarEntry extends BaseModel
{
    use SoftDeletes;
    protected function casts(): array { return ['starts_at'=>'datetime','ends_at'=>'datetime','all_day'=>'boolean']; }
}
