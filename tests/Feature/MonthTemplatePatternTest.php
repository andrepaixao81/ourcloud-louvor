<?php

namespace Tests\Feature;

use App\Models\RecurringServiceRule;
use App\Models\Service;
use App\Models\ServiceType;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthTemplatePatternTest extends TestCase
{
    use RefreshDatabase;

    public function test_month_template_accepts_uuid_creator_and_replaces_second_sunday_with_santa_ceia(): void
    {
        $org = $this->makeOrganization();
        $user = $this->makeUser($org, ['services.create', 'services.view']);

        $teaching = ServiceType::query()->create([
            'organization_id' => $org->id,
            'name' => 'Culto de Ensino',
            'slug' => 'culto-de-ensino',
            'active' => true,
        ]);
        $communion = ServiceType::query()->create([
            'organization_id' => $org->id,
            'name' => 'Santa Ceia',
            'slug' => 'santa-ceia',
            'requires_authorization' => true,
            'active' => true,
        ]);

        $teachingDates = ['2026-09-06', '2026-09-20', '2026-09-27'];
        $teachingServices = collect($teachingDates)->map(fn (string $date) => Service::query()->create([
            'organization_id' => $org->id,
            'service_type_id' => $teaching->id,
            'name' => 'Culto de Ensino',
            'starts_at' => Carbon::parse($date.' 09:00', 'America/Sao_Paulo'),
            'ends_at' => Carbon::parse($date.' 11:00', 'America/Sao_Paulo'),
            'timezone' => 'America/Sao_Paulo',
            'status' => 'scheduled',
            'origin' => 'manual',
            'created_by' => $user->id,
        ]));

        $santaCeia = Service::query()->create([
            'organization_id' => $org->id,
            'service_type_id' => $communion->id,
            'name' => 'Santa Ceia',
            'starts_at' => Carbon::parse('2026-09-13 09:00', 'America/Sao_Paulo'),
            'ends_at' => Carbon::parse('2026-09-13 11:00', 'America/Sao_Paulo'),
            'timezone' => 'America/Sao_Paulo',
            'status' => 'scheduled',
            'origin' => 'manual',
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->post('/services/month-template', [
            'month' => '2026-09',
            'horizon' => '3',
            'patterns' => [
                ['service_id' => $teachingServices->first()->id, 'mode' => 'weekly'],
                ['service_id' => $santaCeia->id, 'mode' => 'monthly_nth_2'],
            ],
        ]);

        $response->assertRedirect('/services?month=2026-10');

        $weeklyRule = RecurringServiceRule::query()
            ->where('service_type_id', $teaching->id)
            ->where('frequency', 'weekly')
            ->firstOrFail();
        $this->assertSame([2], $weeklyRule->excluded_weeks_of_month);

        $this->assertDatabaseHas('recurring_service_rules', [
            'service_type_id' => $communion->id,
            'frequency' => 'monthly_nth',
            'week_of_month' => 2,
        ]);

        $octoberServices = Service::query()
            ->where('origin', 'recurrence')
            ->whereBetween('starts_at', [
                Carbon::parse('2026-10-01 00:00', 'America/Sao_Paulo'),
                Carbon::parse('2026-10-31 23:59:59', 'America/Sao_Paulo'),
            ])
            ->orderBy('starts_at')
            ->get();

        $secondSunday = $octoberServices->filter(fn (Service $service) => $service->starts_at->toDateString() === '2026-10-11' && $service->starts_at->format('H:i') === '09:00');
        $this->assertCount(1, $secondSunday);
        $this->assertSame($communion->id, $secondSunday->first()->service_type_id);
    }
}
