<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Person extends BaseModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['joined_at' => 'date', 'birth_date' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function congregation(): BelongsTo
    {
        return $this->belongsTo(Congregation::class);
    }

    public function ministryRoles(): BelongsToMany
    {
        return $this->belongsToMany(MinistryRole::class, 'person_ministry_role');
    }

    public function songKeys(): HasMany
    {
        return $this->hasMany(MinisterSongKey::class);
    }

    public function servicePermissions(): HasMany
    {
        return $this->hasMany(MinisterServicePermission::class);
    }

    public function isMinister(): bool
    {
        if ($this->relationLoaded('ministryRoles')) {
            return $this->ministryRoles->contains(fn (MinistryRole $role) => $role->is_minister_role && $role->active);
        }

        return $this->ministryRoles()
            ->where('is_minister_role', true)
            ->where('active', true)
            ->exists();
    }

    /** @return array<string> */
    public function allowedServiceTypeIds(): array
    {
        if ($this->relationLoaded('servicePermissions')) {
            return $this->servicePermissions
                ->where('allowed', true)
                ->pluck('service_type_id')
                ->values()
                ->all();
        }

        return $this->servicePermissions()
            ->where('allowed', true)
            ->pluck('service_type_id')
            ->all();
    }
}
