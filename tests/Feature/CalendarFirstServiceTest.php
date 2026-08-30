<?php

namespace Tests\Feature;

use App\Models\ServiceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarFirstServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_quick_calendar_creation_uses_service_type_as_default_name(): void
    {
        $org = $this->makeOrganization();
        $user = $this->makeUser($org, ['services.create', 'services.view']);
        $type = ServiceType::query()->create([
            'organization_id' => $org->id,
            'name' => 'Culto de Celebração',
            'slug' => 'culto-celebracao',
            'active' => true,
        ]);

        $this->actingAs($user)->post('/services', [
            'service_type_id' => $type->id,
            'service_date' => '2026-09-06',
            'starts_time' => '19:00',
            'ends_time' => '20:30',
            'return_to' => 'calendar',
        ])->assertRedirect('/services?month=2026-09');

        $this->assertDatabaseHas('services', [
            'service_type_id' => $type->id,
            'name' => 'Culto de Celebração',
            'status' => 'scheduled',
            'origin' => 'manual',
        ]);
    }
}
