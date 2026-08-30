<?php

namespace App\Models;

class ActionItem extends BaseModel
{
    protected function casts(): array { return ['payload'=>'array','due_at'=>'datetime','read_at'=>'datetime','completed_at'=>'datetime']; }
}
