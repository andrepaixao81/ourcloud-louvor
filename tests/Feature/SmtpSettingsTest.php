<?php

namespace Tests\Feature;

use App\Models\ApplicationSetting;
use App\Services\OrganizationMailSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmtpSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_smtp_password_is_not_stored_in_plain_text(): void
    {
        $org = $this->makeOrganization();
        $user = $this->makeUser($org);
        $this->actingAs($user);

        app(OrganizationMailSettingsService::class)->save($org->id, [
            'enabled' => true,
            'host' => 'smtp.example.test',
            'port' => 587,
            'username' => 'sgml@example.test',
            'password' => 'SegredoSMTP!123',
            'security' => 'smtp',
            'from_address' => 'sgml@example.test',
            'from_name' => 'SGML',
            'ehlo_domain' => 'example.test',
        ]);

        $stored = ApplicationSetting::query()->withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('key', 'smtp.password')
            ->firstOrFail();

        $this->assertNotSame('SegredoSMTP!123', $stored->value);
        $this->assertTrue($stored->is_secret);
        $this->assertSame('SegredoSMTP!123', app(OrganizationMailSettingsService::class)->get($org->id, true)['password']);
    }
}
