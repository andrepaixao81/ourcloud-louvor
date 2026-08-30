<?php
namespace Tests\Feature;
use App\Models\Repertoire;
use App\Models\RepertoireItem;
use App\Models\Service;
use App\Models\ServiceExecution;
use App\Models\ServiceType;
use App\Models\Song;
use App\Services\ExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class ExecutionHistoryTest extends TestCase
{
    use RefreshDatabase;
    public function test_planned_song_does_not_count_as_performed_until_execution(): void
    {
        $org=$this->makeOrganization(); $user=$this->makeUser($org); $this->actingAs($user);
        $type=ServiceType::query()->create(['organization_id'=>$org->id,'name'=>'Culto','slug'=>'culto','active'=>true]);
        $service=Service::query()->create(['organization_id'=>$org->id,'service_type_id'=>$type->id,'name'=>'Culto','starts_at'=>now(),'timezone'=>'America/Sao_Paulo','status'=>'draft','origin'=>'manual']);
        $song=Song::query()->create(['organization_id'=>$org->id,'title'=>'Louvor A','normalized_title'=>'louvor a','status'=>'active']);
        $rep=Repertoire::query()->create(['organization_id'=>$org->id,'service_id'=>$service->id,'status'=>'draft','revision_number'=>1]);
        $item=RepertoireItem::query()->create(['organization_id'=>$org->id,'repertoire_id'=>$rep->id,'song_id'=>$song->id,'position'=>1,'planned_key'=>'G']);
        $this->assertDatabaseCount('song_executions',0);
        $execution=app(ExecutionService::class)->start($service);
        app(ExecutionService::class)->performFromRepertoire($execution,$item,'F');
        $this->assertDatabaseHas('song_executions',['song_id'=>$song->id,'planned_key'=>'G','performed_key'=>'F','execution_source'=>'repertoire']);
    }
}
