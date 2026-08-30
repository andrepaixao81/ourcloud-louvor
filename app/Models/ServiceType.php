<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceType extends BaseModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'requires_authorization' => 'boolean',
            'manual_minister_assignment' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function ministerPermissions(): HasMany
    {
        return $this->hasMany(MinisterServicePermission::class);
    }
}
