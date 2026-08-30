<?php

namespace App\Models;

class ApplicationSetting extends BaseModel
{
    protected function casts(): array { return ['is_secret'=>'boolean']; }
}
