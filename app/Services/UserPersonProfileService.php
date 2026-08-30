<?php

namespace App\Services;

use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserPersonProfileService
{
    public function ensure(User $user, ?Person $preferredPerson = null): Person
    {
        return DB::transaction(function () use ($user, $preferredPerson): Person {
            $lockedUser = User::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($user->id);

            $person = Person::query()->withoutGlobalScopes()
                ->where('organization_id', $lockedUser->organization_id)
                ->where('user_id', $lockedUser->id)
                ->first();

            if (! $person && $preferredPerson) {
                if ($preferredPerson->organization_id !== $lockedUser->organization_id) {
                    throw ValidationException::withMessages(['person_id' => 'A pessoa selecionada pertence a outra organização.']);
                }
                if ($preferredPerson->user_id && $preferredPerson->user_id !== $lockedUser->id) {
                    throw ValidationException::withMessages(['person_id' => 'A pessoa selecionada já possui outra conta de acesso.']);
                }
                $person = Person::query()->withoutGlobalScopes()->findOrFail($preferredPerson->id);
            }

            if (! $person) {
                $person = Person::query()->withoutGlobalScopes()
                    ->where('organization_id', $lockedUser->organization_id)
                    ->whereNull('user_id')
                    ->whereNotNull('email')
                    ->whereRaw('LOWER(email) = LOWER(?)', [$lockedUser->email])
                    ->orderBy('created_at')
                    ->first();
            }

            if (! $person) {
                [$firstName, $lastName] = $this->splitName($lockedUser->name);
                $person = Person::query()->withoutGlobalScopes()->create([
                    'organization_id' => $lockedUser->organization_id,
                    'user_id' => $lockedUser->id,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'display_name' => $lockedUser->name,
                    'email' => $lockedUser->email,
                    'status' => 'active',
                ]);
            } else {
                $updates = ['user_id' => $lockedUser->id];
                if (! $person->email) $updates['email'] = $lockedUser->email;
                $person->forceFill($updates)->save();
            }

            $user->setRelation('person', $person);
            return $person;
        });
    }

    /** @return array{0:string,1:?string} */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\\s+/', trim($name)) ?: [];
        $first = array_shift($parts) ?: 'Usuário';
        $last = $parts ? implode(' ', $parts) : null;
        return [$first, $last];
    }
}
