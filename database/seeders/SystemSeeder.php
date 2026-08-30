<?php

namespace Database\Seeders;

use App\Models\Congregation;
use App\Models\Location;
use App\Models\MinistryRole;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServiceType;
use App\Models\SongCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class SystemSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::query()->firstOrCreate(
            ['slug' => env('SGML_ORGANIZATION_SLUG', 'igreja-local')],
            [
                'name' => env('SGML_ORGANIZATION_NAME', 'Igreja Local'),
                'timezone' => env('APP_TIMEZONE', 'America/Sao_Paulo'),
                'active' => true,
            ]
        );

        $permissions = $this->seedPermissions();
        $roles = $this->seedRoles($organization, $permissions);
        $this->seedCatalogs($organization);
        $this->seedAdmin($organization, $roles['administrator']);
    }

    /** @return array<string, Permission> */
    private function seedPermissions(): array
    {
        $definitions = [
            // Usuários e estrutura
            'users.view' => 'Visualizar pessoas e usuários',
            'users.create' => 'Criar pessoas e usuários',
            'users.update' => 'Atualizar pessoas e usuários',
            'users.disable' => 'Arquivar/desativar pessoas e usuários',
            'users.permissions' => 'Administrar papéis e permissões de usuários',
            // Escala
            'schedule.view' => 'Visualizar escalas',
            'schedule.create' => 'Criar escalas',
            'schedule.generate' => 'Gerar sugestão automática de escala',
            'schedule.update' => 'Alterar escala em revisão',
            'schedule.publish' => 'Publicar escala',
            'schedule.cancel' => 'Cancelar escala',
            'schedule.swap' => 'Solicitar/acompanhar trocas',
            'schedule.force_swap' => 'Aprovar ou efetivar troca administrativa',
            'schedule.reset' => 'Revogar/resetar escala para gerar nova versão',
            'schedule.manual_assign' => 'Definir ministro manualmente em culto especial',
            'schedule.additional_ministers' => 'Definir ministros adicionais em cultos especiais',
            'agenda.view' => 'Visualizar a própria agenda ministerial',
            'availability.manage' => 'Gerenciar a própria disponibilidade',
            'published_schedule.view' => 'Visualizar a escala publicada do ministério',
            // Cultos
            'services.view' => 'Visualizar cultos',
            'services.create' => 'Criar cultos e recorrências',
            'services.update' => 'Atualizar cultos',
            'services.cancel' => 'Cancelar cultos',
            'services.delete' => 'Excluir logicamente cultos sem histórico',
            'services.restore' => 'Restaurar cultos excluídos logicamente',
            // Repertório
            'repertoire.view' => 'Visualizar repertórios',
            'repertoire.manage_own' => 'Gerenciar repertório de cultos sob responsabilidade',
            'repertoire.manage_all' => 'Gerenciar todos os repertórios',
            // Louvores
            'songs.view' => 'Visualizar biblioteca de louvores',
            'songs.request' => 'Solicitar novo louvor',
            'songs.create' => 'Cadastrar louvores',
            'songs.update' => 'Atualizar louvores',
            'songs.approve' => 'Aprovar/rejeitar solicitações de louvores',
            'songs.archive' => 'Excluir louvores da biblioteca preservando histórico',
            'song_history.view' => 'Visualizar histórico de louvores ministrados, ministros e tons',
            // Ensaios
            'rehearsals.view' => 'Visualizar louvores em ensaio',
            'rehearsals.manage' => 'Gerenciar louvores em ensaio',
            // Participação/execução
            'participation.view' => 'Visualizar execução e participantes do culto',
            'participation.register' => 'Permissão legada de registro em execução',
            'participation.update' => 'Permissão legada de atualização de execução',
            'participation.execute_own' => 'Operar o Modo Culto somente da própria escala publicada',
            'participation.manage_execution' => 'Administrar Modo Culto de qualquer culto',
            'participation.manage_roster' => 'Registrar ministros, músicos, backs e participantes efetivos',
            // Calendário
            'calendar.view' => 'Visualizar calendário institucional',
            'calendar.export' => 'Exportar calendário/ICS',
            'calendar_event.create' => 'Criar evento interno de calendário',
            'calendar_event.update' => 'Alterar evento interno de calendário',
            'calendar_event.cancel' => 'Cancelar programação criada diretamente na Agenda',
            'calendar_event.delete' => 'Excluir programação criada diretamente na Agenda',
            // Eventos especiais
            'special_events.view' => 'Visualizar eventos especiais do ministério',
            'special_events.respond' => 'Confirmar participação em eventos especiais',
            'special_events.manage' => 'Criar, alterar e administrar eventos especiais',
            // Aniversários
            'birthdays.view' => 'Visualizar aniversariantes do mês sem ano/idade',
            'birthdays.manage' => 'Configurar mensagem automática de aniversário',
            // Relatórios
            'reports.dashboard' => 'Visualizar dashboard e relatórios gerais',
            'reports.ministers' => 'Visualizar relatórios de ministros',
            'reports.songs' => 'Visualizar relatórios de louvores',
            'reports.participation' => 'Visualizar relatórios de participação',
            // Administração
            'administration.settings' => 'Administrar cadastros e configurações',
            'administration.calendar' => 'Administrar calendário institucional',
            'administration.integrations' => 'Administrar integrações externas',
            'administration.audit' => 'Visualizar trilha de auditoria',
            'administration.mail' => 'Administrar SMTP e e-mails do sistema',
            'administration.whatsapp' => 'Configurar avisos automáticos pelo WhatsApp (Evolution API ou Meta Cloud API)',
        ];

        $result = [];
        foreach ($definitions as $name => $description) {
            $permission = Permission::query()->firstOrCreate(
                ['name' => $name],
                ['module' => Str::before($name, '.'), 'description' => $description]
            );
            $result[$name] = $permission;
        }

        return $result;
    }

    /** @param array<string, Permission> $permissions */
    private function seedRoles(Organization $organization, array $permissions): array
    {
        $roleDefinitions = [
            'administrator' => ['Administrador', array_keys($permissions)],
            'ministry_manager' => ['Responsável pelo Ministério', array_values(array_diff(array_keys($permissions), ['users.permissions', 'administration.audit', 'administration.mail', 'administration.whatsapp', 'participation.manage_execution', 'participation.manage_roster']))],
            'minister' => ['Ministro', [
                'agenda.view','availability.manage','published_schedule.view','schedule.view','schedule.swap','services.view','repertoire.view','repertoire.manage_own','songs.view','songs.request',
                'participation.view','participation.execute_own','calendar.export','rehearsals.view','song_history.view','special_events.view','special_events.respond','birthdays.view',
            ]],
            'participant' => ['Participante do Louvor', ['availability.manage','published_schedule.view','repertoire.view','songs.view','rehearsals.view','song_history.view','special_events.view','special_events.respond','birthdays.view']],
            'member' => ['Membro', []],
            'leadership' => ['Liderança/Pastor', ['agenda.view','availability.manage','published_schedule.view','schedule.view','services.view','repertoire.view','songs.view','participation.view','calendar.view','rehearsals.view','song_history.view','special_events.view','special_events.respond','birthdays.view']],
        ];

        $roles = [];
        foreach ($roleDefinitions as $slug => [$name, $permissionNames]) {
            $role = Role::query()->firstOrCreate(
                ['organization_id' => $organization->id, 'slug' => $slug],
                ['name' => $name, 'description' => $name, 'system' => true]
            );
            $role->permissions()->sync(collect($permissionNames)->map(fn ($permission) => $permissions[$permission]->id)->all());
            $roles[$slug] = $role;
        }

        return $roles;
    }

    private function seedCatalogs(Organization $organization): void
    {
        $congregation = Congregation::query()->firstOrCreate(
            ['organization_id' => $organization->id, 'name' => 'Congregação Principal'],
            ['code' => 'PRINCIPAL', 'active' => true]
        );

        Location::query()->firstOrCreate(
            ['organization_id' => $organization->id, 'name' => 'Templo Principal'],
            ['congregation_id' => $congregation->id, 'active' => true]
        );

        $serviceTypes = [
            ['Culto de Ensino', false, false],
            ['Domingo das Famílias', false, false],
            ['Santa Ceia', true, false],
            ['Deus em Primeiro Lugar', false, false],
            ['Rede Jovem', false, false],
            ['Rede de Mulheres', false, true],
            ['Culto Especial', false, false],
        ];
        foreach ($serviceTypes as [$name, $requiresAuthorization, $manualMinister]) {
            ServiceType::query()->firstOrCreate(
                ['organization_id' => $organization->id, 'slug' => Str::slug($name)],
                ['name' => $name, 'requires_authorization' => $requiresAuthorization, 'manual_minister_assignment' => $manualMinister, 'active' => true]
            );
        }

        $ministryRoles = [
            ['Ministro', true], ['Back Vocal', false], ['Teclado', false], ['Bateria', false],
            ['Baixo', false], ['Guitarra', false], ['Violão', false], ['Percussão', false],
            ['Sonoplasta', false], ['Líder', false], ['Pastor', false],
        ];
        foreach ($ministryRoles as [$name, $isMinister]) {
            MinistryRole::query()->firstOrCreate(
                ['organization_id' => $organization->id, 'slug' => Str::slug($name)],
                ['name' => $name, 'is_minister_role' => $isMinister, 'active' => true]
            );
        }

        foreach (['Adoração','Celebração','Comunhão','Santa Ceia','Oferta','Missões','Natal','Páscoa','Congregacional','Infantil','Outros'] as $category) {
            SongCategory::query()->firstOrCreate(
                ['organization_id' => $organization->id, 'name' => $category],
                ['description' => null, 'active' => true]
            );
        }
    }

    private function seedAdmin(Organization $organization, Role $administrator): void
    {
        $email = trim((string) env('SGML_ADMIN_EMAIL', ''));
        $password = (string) env('SGML_ADMIN_PASSWORD', '');
        $name = trim((string) env('SGML_ADMIN_NAME', 'Administrador'));

        if ($email === '' && $password === '') {
            $this->command?->warn('SGML_ADMIN_EMAIL/SGML_ADMIN_PASSWORD não definidos; usuário inicial não foi criado.');
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('SGML_ADMIN_EMAIL deve conter um e-mail válido.');
        }
        if (mb_strlen($password) < 12) {
            throw new RuntimeException('SGML_ADMIN_PASSWORD deve possuir pelo menos 12 caracteres.');
        }

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'organization_id' => $organization->id,
                'name' => $name !== '' ? $name : 'Administrador',
                'password' => Hash::make($password),
                'active' => true,
                'calendar_feed_token' => Str::random(80),
            ]
        );
        $user->roles()->syncWithoutDetaching([$administrator->id]);
    }
}
