<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserPermissionOverride;
use App\Services\AuditService;
use App\Services\OrganizationMailSettingsService;
use App\Services\UserPersonProfileService;
use App\Services\UserAccountRemovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminUserController extends Controller
{
    public function index(Request $request, OrganizationMailSettingsService $mailSettings)
    {
        $organizationId = $request->user()->organization_id;

        $selectedPerson = null;
        $selectedPersonId = $request->string('person')->toString();
        if ($selectedPersonId !== '' && preg_match('/^[0-9a-fA-F-]{36}$/', $selectedPersonId)) {
            $selectedPerson = Person::query()->find($selectedPersonId);
        }

        $selectedUserId = $request->filled('user') ? $request->string('user')->toString() : null;
        $search = trim($request->string('q')->toString());
        $status = $request->string('status', 'all')->toString();
        if (! in_array($status, ['all', 'active', 'inactive'], true)) $status = 'all';

        $usersQuery = User::query()
            ->where('organization_id', $organizationId)
            ->with(['roles', 'person'])
            ->orderBy('name');

        if ($search !== '') {
            $usersQuery->where(function ($query) use ($search): void {
                $query->where('name', 'ilike', '%'.$search.'%')
                    ->orWhere('email', 'ilike', '%'.$search.'%')
                    ->orWhereHas('person', fn ($person) => $person->where('display_name', 'ilike', '%'.$search.'%'));
            });
        }
        if ($status === 'active') $usersQuery->where('active', true);
        if ($status === 'inactive') $usersQuery->where('active', false);

        $users = $usersQuery->paginate(20)->withQueryString();

        $selectedUser = null;
        if ($selectedUserId && preg_match('/^[0-9a-fA-F-]{36}$/', $selectedUserId)) {
            $selectedUser = User::query()->where('organization_id', $organizationId)->with(['roles', 'person'])->find($selectedUserId);
        }

        $roles = Role::query()->where('organization_id', $organizationId)->with('permissions')->orderBy('name')->get();

        $people = Person::query()
            ->where(function ($query) use ($selectedPerson): void {
                $query->whereNull('user_id');
                if ($selectedPerson) $query->orWhere('id', $selectedPerson->id);
            })
            ->orderBy('display_name')->get();

        $allPeople = Person::query()->with('user')->orderBy('display_name')->get();
        $permissions = Permission::query()->orderBy('module')->orderBy('name')->get();
        $permissionGroups = $permissions->groupBy('module');
        $overrides = collect();
        if ($selectedUser) {
            $overrides = UserPermissionOverride::query()->where('user_id', $selectedUser->id)->with('permission')->get();
        }

        $smtpConfigured = $mailSettings->isConfigured($organizationId);

        return view('admin.users', compact(
            'users', 'roles', 'people', 'allPeople', 'permissions', 'permissionGroups',
            'overrides', 'selectedPerson', 'selectedUserId', 'selectedUser', 'smtpConfigured',
            'search', 'status'
        ));
    }

    public function store(Request $request, AuditService $audit, OrganizationMailSettingsService $mailSettings, UserPersonProfileService $profiles)
    {
        $organizationId = $request->user()->organization_id;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'birth_date' => ['nullable', 'date', 'after_or_equal:1900-01-01', 'before_or_equal:today'],
            'delivery_mode' => ['required', Rule::in(['manual', 'generated_email'])],
            'password' => ['nullable', 'required_if:delivery_mode,manual', 'string', 'min:12'],
            'force_change_password' => ['nullable', 'boolean'],
            'person_id' => [
                'nullable',
                'uuid',
                Rule::exists('people', 'id')->where('organization_id', $organizationId)->whereNull('deleted_at'),
            ],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['uuid'],
        ]);

        $data['email'] = Str::lower($data['email']);
        $data['phone'] = $this->normalizeBrazilianPhone($data['phone'] ?? null);

        $this->assertRolesBelongToOrganization($data['roles'], $organizationId);
        $this->assertRolesAreGrantable($request->user(), $data['roles']);

        if ($data['delivery_mode'] === 'generated_email' && ! $mailSettings->isConfigured($organizationId)) {
            throw ValidationException::withMessages([
                'delivery_mode' => 'Configure e teste o SMTP antes de escolher o envio automático de senha por e-mail.',
            ]);
        }

        $person = null;
        if (! empty($data['person_id'])) {
            $person = Person::query()->findOrFail($data['person_id']);
            if ($person->user_id) {
                throw ValidationException::withMessages([
                    'person_id' => 'Esta pessoa já possui uma conta de acesso vinculada.',
                ]);
            }
        }

        $temporaryPassword = $data['delivery_mode'] === 'generated_email'
            ? $this->generateTemporaryPassword()
            : (string) $data['password'];

        try {
            $user = DB::transaction(function () use ($request, $data, $person, $audit, $organizationId, $temporaryPassword, $mailSettings, $profiles): User {
                $mustChange = $data['delivery_mode'] === 'generated_email' || $request->boolean('force_change_password');

                $user = User::query()->create([
                    'organization_id' => $organizationId,
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make($temporaryPassword),
                    'active' => true,
                    'must_change_password' => $mustChange,
                ]);
                $user->roles()->sync($data['roles']);

                $linkedPerson = $profiles->ensure($user, $person);
                $linkedPerson->forceFill([
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'birth_date' => $data['birth_date'] ?: null,
                ])->save();

                $audit->event('user.created', $user, null, [
                    'roles' => $data['roles'],
                    'person_id' => $linkedPerson->id,
                    'credential_delivery' => $data['delivery_mode'],
                    'must_change_password' => $mustChange,
                ]);

                if ($data['delivery_mode'] === 'generated_email') {
                    $mailSettings->sendTemporaryPassword($user, $temporaryPassword);
                }

                return $user;
            });
        } catch (\Throwable $e) {
            report($e);
            throw ValidationException::withMessages([
                'delivery_mode' => $data['delivery_mode'] === 'generated_email'
                    ? 'Não foi possível enviar a senha temporária. A criação foi cancelada. Verifique o SMTP e tente novamente.'
                    : 'Não foi possível criar o acesso. Verifique os dados e tente novamente.',
            ]);
        }

        $message = $data['delivery_mode'] === 'generated_email'
            ? 'Acesso criado. A senha temporária foi enviada por e-mail e deverá ser alterada no primeiro login.'
            : 'Acesso criado e cadastro pessoal vinculado automaticamente.';

        return redirect()->route('admin.users', ['user' => $user->id])->with('status', $message);
    }

    public function update(Request $request, User $user, AuditService $audit, UserPersonProfileService $profiles)
    {
        abort_unless($user->organization_id === $request->user()->organization_id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'birth_date' => ['nullable', 'date', 'after_or_equal:1900-01-01', 'before_or_equal:today'],
            'active' => ['nullable', 'boolean'],
            'person_id' => [
                'nullable',
                'uuid',
                Rule::exists('people', 'id')->where('organization_id', $request->user()->organization_id)->whereNull('deleted_at'),
            ],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['uuid'],
        ]);

        $data['email'] = Str::lower($data['email']);
        $data['phone'] = $this->normalizeBrazilianPhone($data['phone'] ?? null);

        $this->assertRolesBelongToOrganization($data['roles'], $request->user()->organization_id);
        $this->assertRolesAreGrantable($request->user(), $data['roles']);

        if ($user->is($request->user()) && ! $request->boolean('active')) {
            throw ValidationException::withMessages([
                'active' => 'Você não pode desativar a própria conta enquanto está autenticado.',
            ]);
        }

        $oldRoles = $user->roles()->pluck('roles.id')->all();
        $oldPerson = $user->person()->first();
        $newPerson = null;

        if (! empty($data['person_id'])) {
            $newPerson = Person::query()->findOrFail($data['person_id']);
            if ($newPerson->user_id && $newPerson->user_id !== $user->id) {
                throw ValidationException::withMessages([
                    'person_id' => 'A pessoa selecionada já está vinculada a outra conta de acesso.',
                ]);
            }
        }

        DB::transaction(function () use ($request, $user, $data, $oldPerson, $newPerson, $profiles): void {
            $user->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'active' => $request->boolean('active'),
            ]);
            $user->roles()->sync($data['roles']);

            // RC82: deixar a seleção vazia não remove mais o cadastro pessoal.
            // Toda conta deve permanecer ligada a uma Pessoa para que e-mail,
            // telefone/WhatsApp e nascimento tenham uma única origem persistente.
            // Só trocamos o vínculo quando outra Pessoa foi escolhida explicitamente.
            if ($newPerson && $oldPerson && $oldPerson->id !== $newPerson->id) {
                $oldPerson->update(['user_id' => null]);
            }

            $preferredPerson = $newPerson ?: $oldPerson;
            $linkedPerson = $profiles->ensure($user, $preferredPerson);
            $linkedPerson->forceFill([
                'email' => $data['email'],
                'phone' => $data['phone'],
                'birth_date' => $data['birth_date'] ?: null,
            ])->save();
        });

        $audit->event('user.permissions.updated', $user, null, [
            'old_roles' => $oldRoles,
            'new_roles' => $data['roles'],
            'active' => $request->boolean('active'),
            'old_person_id' => $oldPerson?->id,
            'new_person_id' => $newPerson?->id,
        ]);

        return back()->with('status', 'Usuário e vínculo ministerial atualizados.');
    }

    public function destroy(Request $request, User $user, AuditService $audit, UserAccountRemovalService $removal)
    {
        abort_unless($user->organization_id === $request->user()->organization_id, 404);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'password' => ['required', 'string'],
            'confirmation' => ['required', 'in:EXCLUIR CONTA'],
            'archive_person' => ['nullable', 'boolean'],
        ]);

        if (! Hash::check($data['password'], $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'Senha administrativa inválida.']);
        }
        if ($user->is($request->user())) {
            throw ValidationException::withMessages(['confirmation' => 'Você não pode remover a própria conta enquanto está autenticado.']);
        }

        if ($user->isAdministrator()) {
            $otherActiveAdmins = User::query()
                ->where('organization_id', $user->organization_id)
                ->where('active', true)
                ->where('id', '!=', $user->id)
                ->whereHas('roles', fn ($query) => $query->where('slug', 'administrator'))
                ->count();
            if ($otherActiveAdmins === 0) {
                throw ValidationException::withMessages(['confirmation' => 'Esta é a última conta administrativa ativa. Crie ou habilite outro Administrador antes de removê-la.']);
            }
        }

        try {
            $result = $removal->remove(
                $user,
                $request->user(),
                (string) $data['reason'],
                $request->boolean('archive_person'),
            );
        } catch (\Throwable $exception) {
            report($exception);
            $reference = strtoupper(substr(hash('sha256', $exception->getMessage().microtime(true)), 0, 10));
            return back()->with('error', 'Não foi possível remover a conta. Nenhuma alteração parcial foi mantida. Referência: '.$reference.'. Consulte o log do servidor se o problema persistir.');
        }

        return redirect()->route('admin.users')->with('status', ($result['archived_person'] ?? false)
            ? 'Conta removida e cadastro pessoal arquivado. O histórico operacional foi preservado.'
            : 'Conta removida do sistema. O cadastro da pessoa e o histórico ministerial foram preservados.');
    }

    public function resetPassword(Request $request, User $user, AuditService $audit, OrganizationMailSettingsService $mailSettings)
    {
        abort_unless($user->organization_id === $request->user()->organization_id, 404);
        $data = $request->validate([
            'mode' => ['required', Rule::in(['manual', 'generated_email'])],
            'password' => ['nullable', 'required_if:mode,manual', 'string', 'min:12'],
        ]);

        if ($data['mode'] === 'generated_email' && ! $mailSettings->isConfigured($user->organization_id)) {
            throw ValidationException::withMessages(['mode' => 'SMTP não está configurado ou habilitado.']);
        }

        $password = $data['mode'] === 'generated_email' ? $this->generateTemporaryPassword() : (string) $data['password'];

        try {
            DB::transaction(function () use ($user, $password, $data, $mailSettings): void {
                $user->update([
                    'password' => Hash::make($password),
                    'must_change_password' => true,
                ]);

                if ($data['mode'] === 'generated_email') {
                    $mailSettings->sendTemporaryPassword($user, $password);
                }
            });
        } catch (\Throwable $e) {
            report($e);
            throw ValidationException::withMessages(['mode' => 'Falha ao redefinir/enviar a senha. Nenhuma alteração foi mantida.']);
        }

        $audit->event('user.password.reset_by_admin', $user, null, ['mode' => $data['mode']]);

        return back()->with('status', $data['mode'] === 'generated_email'
            ? 'Nova senha temporária enviada. O usuário deverá alterá-la no próximo login.'
            : 'Senha temporária redefinida. O usuário deverá alterá-la no próximo login.');
    }

    public function storeRole(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'permissions' => ['array'],
            'permissions.*' => ['uuid'],
        ]);

        $permissionIds = $data['permissions'] ?? [];
        $this->assertPermissionsAreGrantable($request->user(), $permissionIds);

        $role = Role::query()->create([
            'organization_id' => $request->user()->organization_id,
            'name' => $data['name'],
            'slug' => str()->slug($data['name']).'-'.str()->lower(str()->random(4)),
            'description' => 'Papel personalizado',
            'system' => false,
        ]);
        $role->permissions()->sync($permissionIds);
        $audit->event('role.created', $role, null, ['permissions' => $permissionIds]);

        return back()->with('status', 'Papel criado.');
    }

    public function updateRole(Request $request, Role $role, AuditService $audit)
    {
        abort_unless($role->organization_id === $request->user()->organization_id, 404);
        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['uuid'],
        ]);

        $permissionIds = $data['permissions'] ?? [];
        $this->assertPermissionsAreGrantable($request->user(), $permissionIds);

        $old = $role->permissions()->pluck('permissions.id')->all();
        $role->permissions()->sync($permissionIds);
        $audit->event('role.permissions.updated', $role, null, [
            'old_permissions' => $old,
            'new_permissions' => $permissionIds,
        ]);

        return back()->with('status', 'Permissões do papel atualizadas.');
    }

    public function groups(Request $request)
    {
        $organizationId = $request->user()->organization_id;
        $roles = Role::query()
            ->where('organization_id', $organizationId)
            ->with([
                'permissions:id,name,description,module',
                'users' => fn ($query) => $query->orderBy('name'),
            ])
            ->orderByDesc('system')
            ->orderBy('name')
            ->get();

        $selectedRoleId = $request->string('role')->toString();
        $selectedRole = $roles->firstWhere('id', $selectedRoleId) ?: $roles->first();
        $availableUsers = collect();
        if ($selectedRole) {
            $currentIds = $selectedRole->users->pluck('id')->all();
            $availableUsers = User::query()
                ->where('organization_id', $organizationId)
                ->when($currentIds !== [], fn ($query) => $query->whereNotIn('id', $currentIds))
                ->orderBy('name')
                ->get();
        }

        return view('admin.groups', compact('roles', 'selectedRole', 'availableUsers'));
    }

    public function addRoleMember(Request $request, Role $role, AuditService $audit)
    {
        abort_unless($role->organization_id === $request->user()->organization_id, 404);
        $data = $request->validate([
            'user_id' => [
                'required',
                'uuid',
                Rule::exists('users', 'id')->where('organization_id', $request->user()->organization_id)->whereNull('deleted_at'),
            ],
        ]);

        $this->assertRolesAreGrantable($request->user(), [$role->id]);
        $user = User::query()->where('organization_id', $request->user()->organization_id)->findOrFail($data['user_id']);
        $alreadyMember = $user->roles()->where('roles.id', $role->id)->exists();
        if (! $alreadyMember) {
            $user->roles()->attach($role->id);
            $audit->event('role.member.added', $user, null, [
                'role_id' => $role->id,
                'role_name' => $role->name,
            ]);
        }

        return redirect()->route('admin.groups', ['role' => $role->id])
            ->with('status', $alreadyMember ? 'O usuário já pertence a este grupo.' : 'Usuário adicionado ao grupo de acesso.');
    }

    public function removeRoleMember(Request $request, Role $role, User $user, AuditService $audit)
    {
        abort_unless(
            $role->organization_id === $request->user()->organization_id
            && $user->organization_id === $request->user()->organization_id,
            404
        );

        if ($user->is($request->user()) && $role->permissions()->where('permissions.name', 'users.permissions')->exists()) {
            throw ValidationException::withMessages([
                'group' => 'Para evitar bloqueio administrativo, sua própria conta não pode ser removida por esta tela de um grupo que gerencia usuários e permissões.',
            ]);
        }

        if ($user->roles()->where('roles.id', $role->id)->exists()) {
            $user->roles()->detach($role->id);
            $audit->event('role.member.removed', $user, null, [
                'role_id' => $role->id,
                'role_name' => $role->name,
            ]);
        }

        return redirect()->route('admin.groups', ['role' => $role->id])
            ->with('status', 'Usuário removido do grupo de acesso.');
    }

    public function setOverride(Request $request, User $user, AuditService $audit)
    {
        abort_unless($user->organization_id === $request->user()->organization_id, 404);
        $data = $request->validate([
            'permission_id' => ['required', 'uuid', 'exists:permissions,id'],
            'allowed' => ['required', 'boolean'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        if ((bool) $data['allowed']) {
            $this->assertPermissionsAreGrantable($request->user(), [$data['permission_id']]);
        }

        $override = UserPermissionOverride::query()->updateOrCreate(
            ['user_id' => $user->id, 'permission_id' => $data['permission_id']],
            [
                'allowed' => (bool) $data['allowed'],
                'expires_at' => $data['expires_at'] ?? null,
                'reason' => $data['reason'],
            ]
        );

        $audit->event('user.permission_override.updated', $user, $data['reason'], [
            'permission_id' => $data['permission_id'],
            'allowed' => (bool) $data['allowed'],
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        return back()->with('status', 'ACL individual atualizada.');
    }

    public function deleteOverride(Request $request, User $user, UserPermissionOverride $override, AuditService $audit)
    {
        abort_unless(
            $user->organization_id === $request->user()->organization_id && $override->user_id === $user->id,
            404
        );

        $permissionId = $override->permission_id;
        $override->delete();
        $audit->event('user.permission_override.deleted', $user, null, ['permission_id' => $permissionId]);

        return back()->with('status', 'ACL individual removida.');
    }

    private function assertRolesBelongToOrganization(array $roleIds, string $organizationId): void
    {
        $valid = Role::query()->where('organization_id', $organizationId)->whereIn('id', $roleIds)->count();
        if ($valid !== count(array_unique($roleIds))) {
            throw ValidationException::withMessages([
                'roles' => 'Um ou mais papéis informados não pertencem à organização atual.',
            ]);
        }
    }

    private function assertRolesAreGrantable(User $actor, array $roleIds): void
    {
        $permissionIds = Role::query()
            ->whereIn('id', $roleIds)
            ->with('permissions:id,name')
            ->get()
            ->flatMap(fn (Role $role) => $role->permissions->pluck('id'))
            ->unique()
            ->values()
            ->all();

        $this->assertPermissionsAreGrantable($actor, $permissionIds);
    }

    private function assertPermissionsAreGrantable(User $actor, array $permissionIds): void
    {
        if ($permissionIds === []) {
            return;
        }

        $permissions = Permission::query()->whereIn('id', $permissionIds)->get(['id', 'name', 'description']);
        $notGrantable = $permissions->reject(fn (Permission $permission) => $actor->hasPermission($permission->name));

        if ($notGrantable->isNotEmpty()) {
            throw ValidationException::withMessages([
                'permissions' => 'Você não pode conceder permissões superiores às que possui: '.$notGrantable->pluck('description')->filter()->join(', '),
            ]);
        }
    }

    private function normalizeBrazilianPhone(?string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', trim((string) $raw)) ?: '';
        if ($digits === '') return null;
        if (in_array(strlen($digits), [12, 13], true) && str_starts_with($digits, '55')) {
            $digits = substr($digits, 2);
        }
        if (! in_array(strlen($digits), [10, 11], true) || (int) substr($digits, 0, 2) < 11) {
            throw ValidationException::withMessages(['phone' => 'Informe DDD + número do telefone. Ex.: (21) 99999-9999.']);
        }
        return $digits;
    }

    private function generateTemporaryPassword(): string
    {
        return 'Sg!'.bin2hex(random_bytes(8)).'9A';
    }
}
