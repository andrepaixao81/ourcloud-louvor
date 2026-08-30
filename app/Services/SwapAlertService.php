<?php

namespace App\Services;

use App\Models\Person;
use App\Models\SwapRequest;
use App\Models\User;

class SwapAlertService
{
    public function pendingCount(User $user, ?Person $contextPerson = null): int
    {
        if (! $user->active || ! $user->hasPermission('schedule.swap')) {
            return 0;
        }

        $query = SwapRequest::query()->where('status', 'requested');

        if ($user->hasPermission('schedule.force_swap')) {
            return $query->count();
        }

        $contextPerson ??= $user->person;
        if (! $contextPerson) {
            return 0;
        }

        return $query->where('target_person_id', $contextPerson->id)->count();
    }
}
