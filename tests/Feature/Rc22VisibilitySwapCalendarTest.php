<?php

namespace Tests\Feature;

use App\Models\CalendarEntry;
use App\Models\MinisterServicePermission;
use App\Models\MinistryRole;
use App\Models\Person;
use App\Models\ScheduleAssignment;
use App\Models\Service;
use App\Models\ServiceType;
use App\Models\SwapRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Rc22VisibilitySwapCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_assignment_is_hidden_from_my_agenda_until_published(): void
    {
        $org = $this->makeOrganization();
        $user = $this->makeUser($org, ['repertoire.view']);
        $person = Person::query()->create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'first_name' => 'André',
            'display_name' => 'André',
            'status' => 'active',
        ]);
        $type = ServiceType::query()->create([
            'organization_id' => $org->id,
            'name' => 'Culto de Ensino',
            'slug' => 'culto-ensino',
            'active' => true,
        ]);
        $service = Service::query()->create([
            'organization_id' => $org->id,
            'service_type_id' => $type->id,
            'name' => 'Culto secreto em rascunho',
            'starts_at' => now()->addWeek(),
            'timezone' => 'America/Sao_Paulo',
            'status' => 'scheduled',
            'origin' => 'manual',
        ]);
        $assignment = ScheduleAssignment::query()->create([
            'organization_id' => $org->id,
            'service_id' => $service->id,
            'minister_person_id' => $person->id,
            'status' => 'draft',
        ]);

        $this->actingAs($user)->get('/my-agenda')
            ->assertOk()
            ->assertDontSee('Culto secreto em rascunho');

        $assignment->update(['status' => 'published', 'published_at' => now(), 'published_by' => $user->id]);

        $this->actingAs($user)->get('/my-agenda')
            ->assertOk()
            ->assertSee('Culto secreto em rascunho');
    }

    public function test_target_acceptance_applies_swap_and_confirms_new_minister(): void
    {
        $org = $this->makeOrganization();
        $requesterUser = $this->makeUser($org, ['schedule.swap', 'repertoire.view']);
        $targetUser = $this->makeUser($org, ['repertoire.view', 'repertoire.manage_own']);

        $role = MinistryRole::query()->create([
            'organization_id' => $org->id,
            'name' => 'Ministro',
            'slug' => 'ministro-test',
            'is_minister_role' => true,
            'active' => true,
        ]);
        $type = ServiceType::query()->create([
            'organization_id' => $org->id,
            'name' => 'Santa Ceia',
            'slug' => 'santa-ceia-test',
            'active' => true,
        ]);
        $requester = Person::query()->create([
            'organization_id' => $org->id,
            'user_id' => $requesterUser->id,
            'first_name' => 'Ana',
            'display_name' => 'Ana',
            'status' => 'active',
        ]);
        $target = Person::query()->create([
            'organization_id' => $org->id,
            'user_id' => $targetUser->id,
            'first_name' => 'Carla',
            'display_name' => 'Carla',
            'status' => 'active',
        ]);
        $requester->ministryRoles()->sync([$role->id]);
        $target->ministryRoles()->sync([$role->id]);
        foreach ([$requester, $target] as $person) {
            MinisterServicePermission::query()->create([
                'organization_id' => $org->id,
                'person_id' => $person->id,
                'service_type_id' => $type->id,
                'allowed' => true,
            ]);
        }
        $service = Service::query()->create([
            'organization_id' => $org->id,
            'service_type_id' => $type->id,
            'name' => 'Santa Ceia',
            'starts_at' => now()->addWeek(),
            'timezone' => 'America/Sao_Paulo',
            'status' => 'scheduled',
            'origin' => 'manual',
        ]);
        $assignment = ScheduleAssignment::query()->create([
            'organization_id' => $org->id,
            'service_id' => $service->id,
            'minister_person_id' => $requester->id,
            'status' => 'published',
            'published_by' => $requesterUser->id,
            'published_at' => now(),
        ]);

        $this->actingAs($requesterUser)->post('/swaps', [
            'schedule_assignment_id' => $assignment->id,
            'target_person_id' => $target->id,
            'reason' => 'Compromisso familiar',
        ])->assertSessionHasNoErrors();

        $swap = SwapRequest::query()->firstOrFail();
        $this->actingAs($targetUser)->post('/swaps/'.$swap->id.'/accept')->assertSessionHasNoErrors();

        $this->assertDatabaseHas('schedule_assignments', [
            'id' => $assignment->id,
            'minister_person_id' => $target->id,
            'status' => 'published',
        ]);
        $this->assertDatabaseHas('swap_requests', ['id' => $swap->id, 'status' => 'accepted']);
        $this->assertDatabaseHas('schedule_confirmations', [
            'schedule_assignment_id' => $assignment->id,
            'person_id' => $target->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_deleting_service_hides_its_calendar_projection(): void
    {
        $org = $this->makeOrganization();
        $user = $this->makeUser($org, ['services.delete']);
        $type = ServiceType::query()->create([
            'organization_id' => $org->id,
            'name' => 'Teste',
            'slug' => 'teste-calendar',
            'active' => true,
        ]);
        $service = Service::query()->create([
            'organization_id' => $org->id,
            'service_type_id' => $type->id,
            'name' => 'Culto para excluir',
            'starts_at' => now()->addWeek(),
            'timezone' => 'America/Sao_Paulo',
            'status' => 'scheduled',
            'origin' => 'manual',
            'created_by' => $user->id,
        ]);
        $entry = CalendarEntry::query()->create([
            'organization_id' => $org->id,
            'source_type' => 'service',
            'source_id' => $service->id,
            'title' => $service->name,
            'starts_at' => $service->starts_at,
            'timezone' => 'America/Sao_Paulo',
            'status' => 'scheduled',
            'visibility' => 'public_internal',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->delete('/services/'.$service->id, ['reason' => 'Cadastro de teste'])
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted('services', ['id' => $service->id]);
        $this->assertSoftDeleted('calendar_entries', ['id' => $entry->id]);
    }
}
