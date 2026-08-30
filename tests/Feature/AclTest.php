<?php
namespace Tests\Feature;
use App\Models\Permission;
use App\Models\UserPermissionOverride;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class AclTest extends TestCase
{
    use RefreshDatabase;
    public function test_explicit_deny_override_wins_over_role_allow(): void
    {
        $org=$this->makeOrganization(); $user=$this->makeUser($org,['songs.view']);
        $permission=Permission::query()->where('name','songs.view')->firstOrFail();
        UserPermissionOverride::query()->create(['user_id'=>$user->id,'permission_id'=>$permission->id,'allowed'=>false,'reason'=>'teste']);
        $this->assertFalse($user->fresh()->hasPermission('songs.view'));
    }
}
