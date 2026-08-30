<?php

namespace App\Services;

use App\Models\Person;
use App\Models\User;

class MinisterAssistanceContext
{
    public const SESSION_KEY = 'sgml.assisted_minister_person_id';
    public const ACTOR_ATTRIBUTE = 'sgml.assistance.actor_user';
    public const PERSON_ATTRIBUTE = 'sgml.assistance.person';
    public const EFFECTIVE_USER_ATTRIBUTE = 'sgml.assistance.effective_user';

    public function actorUser(?User $fallback = null): ?User
    {
        try {
            if (app()->bound('request')) {
                $actor = request()->attributes->get(self::ACTOR_ATTRIBUTE);
                if ($actor instanceof User) return $actor;
            }
        } catch (\Throwable) {
            // CLI/teste sem request completo.
        }

        return $fallback ?? auth()->user();
    }

    public function effectiveUser(?User $fallback = null): ?User
    {
        try {
            if (app()->bound('request')) {
                $effective = request()->attributes->get(self::EFFECTIVE_USER_ATTRIBUTE);
                if ($effective instanceof User) return $effective;
            }
        } catch (\Throwable) {
            // CLI/teste sem request completo.
        }

        $actor = $this->actorUser($fallback);
        $person = $this->assistedPersonForActor($actor);
        return $person?->user?->active ? $person->user : $actor;
    }

    /** Retorna a pessoa operacional efetiva da requisição. */
    public function person(?User $user = null): ?Person
    {
        try {
            if (app()->bound('request')) {
                $person = request()->attributes->get(self::PERSON_ATTRIBUTE);
                if ($person instanceof Person) return $person;
            }
        } catch (\Throwable) {
            // CLI/teste sem request completo.
        }

        $actor = $this->actorUser($user);
        $assisted = $this->assistedPersonForActor($actor);
        if ($assisted) return $assisted;

        return ($user ?? $actor)?->person;
    }

    /** Compatibilidade para views/controllers existentes. */
    public function assistedPerson(?User $user = null): ?Person
    {
        try {
            if (app()->bound('request')) {
                $person = request()->attributes->get(self::PERSON_ATTRIBUTE);
                if ($person instanceof Person) return $person;
            }
        } catch (\Throwable) {
            // CLI/teste sem request completo.
        }

        return $this->assistedPersonForActor($this->actorUser($user));
    }

    public function assistedPersonForActor(?User $actor): ?Person
    {
        if (! $actor || ! $actor->canAssistMinisters()) return null;
        if (! request()->hasSession()) return null;

        $id = session(self::SESSION_KEY);
        if (! is_string($id) || $id === '') return null;

        $person = Person::query()
            ->with(['user.roles.permissions', 'user.person', 'ministryRoles'])
            ->where('organization_id', $actor->organization_id)
            ->where('status', 'active')
            ->whereHas('ministryRoles', fn ($q) => $q->where('is_minister_role', true)->where('active', true))
            ->find($id);

        if (! $person || ! $person->user || ! $person->user->active) {
            session()->forget(self::SESSION_KEY);
            return null;
        }

        return $person;
    }

    public function active(?User $user = null): bool
    {
        return $this->assistedPerson($user) !== null;
    }

    public function start(User $actor, Person $person): void
    {
        abort_unless($actor->canAssistMinisters(), 403);
        $person->loadMissing(['user', 'ministryRoles']);
        abort_unless(
            $person->organization_id === $actor->organization_id
            && $person->status === 'active'
            && $person->isMinister(),
            422,
            'Selecione um ministro ativo desta organização.'
        );
        abort_unless($person->user && $person->user->active, 422, 'Para atuar como o usuário, o ministro precisa possuir uma conta de acesso ativa vinculada.');

        session()->put(self::SESSION_KEY, $person->id);
    }

    public function stop(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
