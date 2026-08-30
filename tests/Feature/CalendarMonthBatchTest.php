<?php

namespace Tests\Feature;

use App\Models\ServiceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarMonthBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_fill_all_occurrences_of_a_weekday_in_the_current_month_without_creating_duplicates(): void
    {
        $org = $this->makeOrganization();
        $user = $this->makeUser($org, ['services.create', 'services.view']);
        $type = ServiceType::query()->create([
            'organization_id' => $org->id,
            'name' => 'Culto de Celebração',
            'slug' => 'culto-celebracao',
            'active' => true,
        ]);

        $payload = [
            'month' => '2026-09',
            'weekday' => 0,
            'service_type_id' => $type->id,
            'starts_time' => '19:00',
            'ends_time' => '20:30',
        ];

        $this->actingAs($user)->post(route('services.month-batch.store'), $payload)->assertRedirect('/services?month=2026-09');
        $this->assertDatabaseCount('services', 4);

        $this->actingAs($user)->post(route('services.month-batch.store'), $payload)->assertRedirect('/services?month=2026-09');
        $this->assertDatabaseCount('services', 4);
    }
}
