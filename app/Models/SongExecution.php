<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SongExecution extends BaseModel
{
    use SoftDeletes;
    protected function casts(): array { return ['started_at'=>'datetime','finished_at'=>'datetime']; }
    public function song(): BelongsTo { return $this->belongsTo(Song::class)->withTrashed(); }
    public function performer(): BelongsTo { return $this->belongsTo(Person::class, 'performed_by_person_id'); }
    public function serviceExecution(): BelongsTo { return $this->belongsTo(ServiceExecution::class); }
}
