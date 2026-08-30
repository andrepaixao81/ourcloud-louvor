<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class UserAccountRemovalService
{
    public function remove(User $user, User $actor, string $reason, bool $archivePerson): array
    {
        $originalName = (string) $user->name;
        $originalEmail = (string) $user->email;
        $personId = DB::table('people')->where('user_id', $user->id)->value('id');

        DB::transaction(function () use ($user, $actor, $reason, $archivePerson, $originalName, $originalEmail, $personId): void {
            // A auditoria é gravada antes da anonimização, mas sem armazenar senha ou
            // qualquer segredo. O alvo pode continuar apontando para o UUID histórico.
            app(AuditService::class)->event('user.account.removed', $user, $reason, [
                'user_name' => $originalName,
                'user_email' => $originalEmail,
                'person_id' => $personId,
                'archive_person' => $archivePerson,
                'removed_by_user_id' => $actor->id,
            ]);

            if ($personId) {
                $personUpdate = [
                    'user_id' => null,
                    'updated_at' => now(),
                ];
                if ($archivePerson) {
                    $personUpdate['status'] = 'inactive';
                    $personUpdate['deleted_at'] = now();
                }
                DB::table('people')->where('id', $personId)->update($personUpdate);
            }

            // Tabelas operacionais podem variar entre versões antigas restauradas.
            // Por isso a remoção de conta é tolerante a schema e não falha porque uma
            // integração opcional não existe no ambiente atual.
            $this->deleteWhereIfPresent('sessions', 'user_id', $user->id);
            $this->deleteWhereIfPresent('calendar_integrations', 'user_id', $user->id);
            $this->deleteWhereIfPresent('calendar_feed_states', 'user_id', $user->id);
            $this->deleteWhereIfPresent('action_items', 'user_id', $user->id);
            $this->deleteWhereIfPresent('user_permission_overrides', 'user_id', $user->id);
            $this->deleteWhereIfPresent('role_user', 'user_id', $user->id);

            if (Schema::hasTable('notifications')) {
                DB::table('notifications')
                    ->where('notifiable_id', $user->id)
                    ->where(function ($query): void {
                        $query->where('notifiable_type', User::class)
                            ->orWhere('notifiable_type', 'App\\Models\\User');
                    })
                    ->delete();
            }
            if (Schema::hasTable('password_reset_tokens')) {
                DB::table('password_reset_tokens')->where('email', $originalEmail)->delete();
            }

            // Não fazemos DELETE físico do usuário: algumas tabelas históricas usam
            // referências RESTRICT. A lápide anonimizada mantém integridade referencial,
            // mas não possui credencial, sessão, papel, feed ou e-mail reutilizável.
            DB::table('users')->where('id', $user->id)->update([
                'name' => 'Conta removida',
                'email' => 'removed-'.substr(hash('sha256', (string) $user->id), 0, 24).'@invalid.local',
                'password' => Hash::make(Str::random(64)),
                'active' => false,
                'must_change_password' => false,
                'calendar_feed_token' => null,
                'remember_token' => null,
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return [
            'person_id' => $personId,
            'archived_person' => $archivePerson && filled($personId),
        ];
    }

    private function deleteWhereIfPresent(string $table, string $column, string $value): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)->where($column, $value)->delete();
    }
}
