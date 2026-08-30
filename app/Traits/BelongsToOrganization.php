<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait BelongsToOrganization
{
    protected static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function (Builder $builder): void {
            if (Auth::check() && Auth::user()->organization_id) {
                $builder->where(
                    $builder->getModel()->qualifyColumn('organization_id'),
                    Auth::user()->organization_id
                );
            }
        });

        static::creating(function ($model): void {
            if (empty($model->organization_id) && Auth::check()) {
                $model->organization_id = Auth::user()->organization_id;
            }
        });
    }
}
