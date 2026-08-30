<?php

namespace App\Services;

use App\Models\Service;
use App\Models\ServiceExecution;
use App\Models\ServiceAdditionalMinister;
use App\Models\User;

class ExecutionAccessService
{
    public function canView(User $user, Service $service): bool
    {
        return $this->canManageAny($user) || $this->isPublishedMinister($user, $service);
    }

    public function canOperate(User $user, Service $service): bool
    {
        if ($this->canManageAny($user)) {
            return true;
        }

        return $user->hasPermission('participation.execute_own')
            && $this->isPublishedMinister($user, $service);
    }

    public function canManageRoster(User $user): bool
    {
        return $user->hasPermission('participation.manage_roster');
    }

    public function canManageAny(User $user): bool
    {
        return $user->hasPermission('participation.manage_execution');
    }

    public function canCancelOpening(User $user, ServiceExecution $execution): bool
    {
        return $this->canOperate($user, $execution->service);
    }

    public function canCorrectSongs(User $user, ServiceExecution $execution): bool
    {
        if ($this->canManageAny($user)) {
            return true;
        }

        return in_array($execution->status, ['in_progress', 'reopened'], true)
            && $execution->service
            && $this->canOperate($user, $execution->service);
    }

    public function isPublishedMinister(User $user, Service $service): bool
    {
        $person = $user->person;
        if (! $person) {
            return false;
        }

        $primary = $service->assignment()
            ->where('status', 'published')
            ->where('minister_person_id', $person->id)
            ->exists();

        if ($primary) return true;

        return ServiceAdditionalMinister::query()
            ->where('service_id', $service->id)
            ->where('person_id', $person->id)
            ->where('status', 'published')
            ->exists();
    }
}
