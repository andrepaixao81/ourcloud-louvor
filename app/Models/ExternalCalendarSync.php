<?php

namespace App\Models;

class ExternalCalendarSync extends BaseModel
{
    protected function casts(): array { return ['last_synced_at'=>'datetime']; }
}
