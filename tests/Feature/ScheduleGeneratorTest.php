<?php

namespace Tests\Feature;

use App\Models\MinisterServicePermission;
use App\Models\MinistryRole;
use App\Models\Person;
use App\Models\ScheduleAssignment;
use App\Models\Service;
use App\Models\ServiceType;
use App\Services\ScheduleGeneratorService;
use App\Services\ScheduleValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_generation_creates_draft_and_never_auto_publishes(): void
    {
        $org = $this->makeOrganization();
        $user = $this->makeUser($org);
        $this->actingAs($user);

        [$role, $type] = $this->makeMinisterRoleAndType($org->id, 'Culto');
        $person = $this->makeMinister($org->id, $role->id, 'Ana');
        $this->allow($org->id, $person->id, $type->id);

        Service::query()->create([
            'organization_id' => $org->id,
            'service_type_id' => $type->id,
            'name' => 'Culto Teste',
            'starts_at' => now()->addWeek(),
            'timezone' => 'America/Sao_Paulo',
            'status' => 'draft',
            'origin' => 'manual',
        ]);

        app(ScheduleGeneratorService::class)->generate(now()->addWeek()->startOfMonth());

        $this->assertDatabaseHas('schedule_assignments', [
            'minister_person_id' => $person->id,
            'status' => 'draft',
        ]);
        $this->assertDatabaseMissing('schedule_assignments', [
            'minister_person_id' => $person->id,
            'status' => 'published',
        ]);
    }

    public function test_minister_without_service_type_authorization_is_not_selected(): void
    {
        $org = $this->makeOrganization();
        $user = $this->makeUser($org);
        $this->actingAs($user);

        [$role, $type] = $this->makeMinisterRoleAndType($org->id, 'Santa Ceia', true);
        $person = $this->makeMinister($org->id, $role->id, 'João');

        $service = Service::query()->create([
            'organization_id' => $org->id,
            'service_type_id' => $type->id,
            'name' => 'Santa Ceia',
            'starts_at' => now()->addWeek(),
            'timezone' => 'America/Sao_Paulo',
            'status' => 'draft',
            'origin' => 'manual',
        ]);

        $result = app(ScheduleGeneratorService::class)->generate(now()->addWeek()->startOfMonth());

        $this->assertDatabaseMissing('schedule_assignments', ['service_id' => $service->id]);
        $this->assertCount(1, $result['unassigned']);

        $this->allow($org->id, $person->id, $type->id);

        app(ScheduleGeneratorService::class)->generate(now()->addWeek()->startOfMonth());

        $this->assertDatabaseHas('schedule_assignments', [
            'service_id' => $service->id,
            'minister_person_id' => $person->id,
        ]);
    }

    public function test_existing_draft_is_reassigned_when_current_minister_loses_service_type_permission(): void
    {
        $org = $this->makeOrganization();
        $user = $this->makeUser($org);
        $this->actingAs($user);

        [$role, $type] = $this->makeMinisterRoleAndType($org->id, 'Deus em Primeiro Lugar');
        $andre = $this->makeMinister($org->id, $role->id, 'André');
        $ana = $this->makeMinister($org->id, $role->id, 'Ana Paula');

        $andrePermission = $this->allow($org->id, $andre->id, $type->id);
        $this->allow($org->id, $ana->id, $type->id);

        $service = Service::query()->create([
            'organization_id' => $org->id,
            'service_type_id' => $type->id,
            'name' => 'Deus em Primeiro Lugar',
            'starts_at' => now()->addWeek(),
            'timezone' => 'America/Sao_Paulo',
            'status' => 'draft',
            'origin' => 'manual',
        ]);

        ScheduleAssignment::query()->create([
            'organization_id' => $org->id,
            'service_id' => $service->id,
            'minister_person_id' => $andre->id,
            'status' => 'draft',
            'score' => 120,
            'explanation' => ['Sugestão antiga'],
            'assigned_by' => $user->id,
        ]);

        // O administrador retira a autorização de André para este culto.
        $andrePermission->delete();

        $result = app(ScheduleGeneratorService::class)->generate($service->starts_at->copy()->startOfMonth());

        $this->assertSame(1, $result['reassigned']);
        $this->assertDatabaseHas('schedule_assignments', [
            'service_id' => $service->id,
            'minister_person_id' => $ana->id,
            'status' => 'draft',
        ]);
        $this->assertDatabaseMissing('schedule_assignments', [
            'service_id' => $service->id,
            'minister_person_id' => $andre->id,
        ]);
    }

    public function test_existing_invalid_draft_is_removed_when_no_eligible_replacement_exists(): void
    {
        $org = $this->makeOrganization();
        $user = $this->makeUser($org);
        $this->actingAs($user);

        [$role, $type] = $this->makeMinisterRoleAndType($org->id, 'Culto de Ensino');
        $andre = $this->makeMinister($org->id, $role->id, 'André');
        $permission = $this->allow($org->id, $andre->id, $type->id);

        $service = Service::query()->create([
            'organization_id' => $org->id,
            'service_type_id' => $type->id,
            'name' => 'Culto de Ensino',
            'starts_at' => now()->addWeek(),
            'timezone' => 'America/Sao_Paulo',
            'status' => 'draft',
            'origin' => 'manual',
        ]);

        ScheduleAssignment::query()->create([
            'organization_id' => $org->id,
            'service_id' => $service->id,
            'minister_person_id' => $andre->id,
            'status' => 'draft',
            'score' => 120,
            'assigned_by' => $user->id,
        ]);

        $permission->delete();

        $result = app(ScheduleGeneratorService::class)->generate($service->starts_at->copy()->startOfMonth());

        $this->assertCount(1, $result['unassigned']);
        $this->assertDatabaseMissing('schedule_assignments', ['service_id' => $service->id]);
    }

    public function test_published_assignment_is_not_silently_changed_but_validation_blocks_it_when_permission_is_removed(): void
    {
        $org = $this->makeOrganization();
        $user = $this->makeUser($org);
        $this->actingAs($user);

        [$role, $type] = $this->makeMinisterRoleAndType($org->id, 'Domingo das Famílias');
        $andre = $this->makeMinister($org->id, $role->id, 'André');
        $permission = $this->allow($org->id, $andre->id, $type->id);

        $service = Service::query()->create([
            'organization_id' => $org->id,
            'service_type_id' => $type->id,
            'name' => 'Domingo das Famílias',
            'starts_at' => now()->addWeek(),
            'timezone' => 'America/Sao_Paulo',
            'status' => 'confirmed',
            'origin' => 'manual',
        ]);

        ScheduleAssignment::query()->create([
            'organization_id' => $org->id,
            'service_id' => $service->id,
            'minister_person_id' => $andre->id,
            'status' => 'published',
            'published_by' => $user->id,
            'published_at' => now(),
            'assigned_by' => $user->id,
        ]);

        $permission->delete();

        $result = app(ScheduleGeneratorService::class)->generate($service->starts_at->copy()->startOfMonth());
        $validation = app(ScheduleValidationService::class)->validateMonth($service->starts_at->copy()->startOfMonth());

        $this->assertCount(1, $result['published_invalid']);
        $this->assertDatabaseHas('schedule_assignments', [
            'service_id' => $service->id,
            'minister_person_id' => $andre->id,
            'status' => 'published',
        ]);
        $this->assertFalse($validation['valid']);
        $this->assertStringContainsString('não está elegível', implode(' | ', $validation['errors']));
    }

    public function test_same_minister_is_never_assigned_to_two_services_on_same_calendar_day(): void
    {
        $org = $this->makeOrganization();
        $user = $this->makeUser($org);
        $this->actingAs($user);

        [$role, $type] = $this->makeMinisterRoleAndType($org->id, 'Domingo');
        $ana = $this->makeMinister($org->id, $role->id, 'Ana');
        $bia = $this->makeMinister($org->id, $role->id, 'Beatriz');
        $this->allow($org->id, $ana->id, $type->id);
        $this->allow($org->id, $bia->id, $type->id);

        $day = now()->addWeek()->startOfDay();
        $morning = Service::query()->create([
            'organization_id' => $org->id,
            'service_type_id' => $type->id,
            'name' => 'Culto da Manhã',
            'starts_at' => $day->copy()->setTime(9, 0),
            'ends_at' => $day->copy()->setTime(11, 0),
            'timezone' => 'America/Sao_Paulo',
            'status' => 'draft',
            'origin' => 'manual',
        ]);
        $evening = Service::query()->create([
            'organization_id' => $org->id,
            'service_type_id' => $type->id,
            'name' => 'Culto da Noite',
            'starts_at' => $day->copy()->setTime(19, 0),
            'ends_at' => $day->copy()->setTime(21, 0),
            'timezone' => 'America/Sao_Paulo',
            'status' => 'draft',
            'origin' => 'manual',
        ]);

        app(ScheduleGeneratorService::class)->generate($day->copy()->startOfMonth());

        $morningAssignment = ScheduleAssignment::query()->where('service_id', $morning->id)->firstOrFail();
        $eveningAssignment = ScheduleAssignment::query()->where('service_id', $evening->id)->firstOrFail();

        $this->assertNotSame(
            $morningAssignment->minister_person_id,
            $eveningAssignment->minister_person_id,
            'Um ministro não pode conduzir dois cultos no mesmo dia, mesmo em horários diferentes.'
        );
    }

    private function makeMinisterRoleAndType(string $organizationId, string $typeName, bool $requiresAuthorization = false): array
    {
        $role = MinistryRole::query()->create([
            'organization_id' => $organizationId,
            'name' => 'Ministro',
            'slug' => 'ministro-'.str()->lower(str()->random(5)),
            'is_minister_role' => true,
            'active' => true,
        ]);

        $type = ServiceType::query()->create([
            'organization_id' => $organizationId,
            'name' => $typeName,
            'slug' => str()->slug($typeName).'-'.str()->lower(str()->random(5)),
            'requires_authorization' => $requiresAuthorization,
            'active' => true,
        ]);

        return [$role, $type];
    }

    private function makeMinister(string $organizationId, string $roleId, string $name): Person
    {
        $person = Person::query()->create([
            'organization_id' => $organizationId,
            'first_name' => $name,
            'display_name' => $name,
            'status' => 'active',
        ]);
        $person->ministryRoles()->sync([$roleId]);

        return $person;
    }

    private function allow(string $organizationId, string $personId, string $serviceTypeId): MinisterServicePermission
    {
        return MinisterServicePermission::query()->create([
            'organization_id' => $organizationId,
            'person_id' => $personId,
            'service_type_id' => $serviceTypeId,
            'allowed' => true,
        ]);
    }
}
