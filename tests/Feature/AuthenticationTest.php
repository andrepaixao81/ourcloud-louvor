<?php
namespace Tests\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class AuthenticationTest extends TestCase
{
    use RefreshDatabase;
    public function test_guest_is_redirected_to_login(): void { $this->get('/')->assertRedirect('/login'); }
    public function test_active_user_can_login(): void { $org=$this->makeOrganization(); $user=$this->makeUser($org); $this->post('/login',['email'=>$user->email,'password'=>'SenhaSegura!123'])->assertRedirect('/'); $this->assertAuthenticatedAs($user); }
}
