<?php

namespace App\Models;

class CalendarIntegration extends BaseModel
{
    protected $hidden = ['access_token_encrypted','refresh_token_encrypted'];
    protected function casts(): array { return ['access_token_encrypted'=>'encrypted','refresh_token_encrypted'=>'encrypted','token_expires_at'=>'datetime','last_sync_at'=>'datetime','last_error_at'=>'datetime','revoked_at'=>'datetime']; }
}
