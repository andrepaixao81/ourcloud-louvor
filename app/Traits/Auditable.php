<?php

namespace App\Traits;

use App\Services\AuditService;

trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(fn ($model) => app(AuditService::class)->modelEvent('created', $model, [], $model->getAttributes()));

        static::updated(function ($model): void {
            $changes = $model->getChanges();
            unset($changes['updated_at']);
            if ($changes === []) {
                return;
            }

            $old = [];
            foreach (array_keys($changes) as $key) {
                $old[$key] = $model->getOriginal($key);
            }

            app(AuditService::class)->modelEvent('updated', $model, $old, $changes);
        });

        static::deleted(fn ($model) => app(AuditService::class)->modelEvent('deleted', $model));

        // O evento "restored" existe apenas em modelos que usam SoftDeletes.
        // Registrar esse evento indiscriminadamente em BaseModel faz o Eloquent
        // cair em __callStatic() durante o próprio boot do model, provocando
        // recursão em bootIfNotBooted() nos modelos sem SoftDeletes.
        if (method_exists(static::class, 'restored')) {
            static::restored(fn ($model) => app(AuditService::class)->modelEvent('restored', $model));
        }
    }
}
