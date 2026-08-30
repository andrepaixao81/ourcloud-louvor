<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ForcedPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_temporary_password_requires_change_after_login(): void
    {
        $org = $this->makeOrganization();
        $user = $this->makeUser($org);
        $user->update(['must_change_password' => true]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'SenhaSegura!123',
        ])->assertRedirect(route('password.required'));

        $this->actingAs($user->fresh())->post(route('password.required.update'), [
            'password' => 'NovaSenhaMuitoForte!456',
            'password_confirmation' => 'NovaSenhaMuitoForte!456',
        ])->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('NovaSenhaMuitoForte!456', $user->password));
    }
}
