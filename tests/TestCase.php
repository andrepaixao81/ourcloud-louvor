<?php

namespace Tests;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;

abstract class TestCase extends BaseTestCase
{
    protected function makeOrganization(string $name = 'Igreja Teste'): Organization
    {
        return Organization::query()->create(['name'=>$name,'slug'=>str()->slug($name).'-'.str()->lower(str()->random(5)),'timezone'=>'America/Sao_Paulo','active'=>true]);
    }

    protected function makeUser(Organization $organization, array $permissions = []): User
    {
        $user=User::query()->create(['organization_id'=>$organization->id,'name'=>'Admin Teste','email'=>str()->lower(str()->random(8)).'@example.test','password'=>Hash::make('SenhaSegura!123'),'active'=>true]);
        if($permissions){
            $role=Role::query()->create(['organization_id'=>$organization->id,'name'=>'Teste','slug'=>'teste-'.str()->lower(str()->random(5)),'system'=>false]);
            $ids=[];
            foreach($permissions as $name){$permission=Permission::query()->firstOrCreate(['name'=>$name],['module'=>str()->before($name,'.'),'description'=>$name]);$ids[]=$permission->id;}
            $role->permissions()->sync($ids);$user->roles()->sync([$role->id]);
        }
        return $user;
    }
}
