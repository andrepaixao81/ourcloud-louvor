<?php
namespace Tests\Feature;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;
    public function test_person_from_another_organization_is_not_resolvable(): void
    {
        $orgA=$this->makeOrganization('Igreja A'); $orgB=$this->makeOrganization('Igreja B');
        $userB=$this->makeUser($orgB,['users.update']);
        $personA=Person::query()->create(['organization_id'=>$orgA->id,'first_name'=>'Pessoa','display_name'=>'Pessoa A','status'=>'active']);
        $this->actingAs($userB)->get(route('people.edit',$personA))->assertNotFound();
    }
}
