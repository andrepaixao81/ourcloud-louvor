<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceExecution extends BaseModel
{
    protected function casts(): array { return ['started_at'=>'datetime','finished_at'=>'datetime','auto_finalized_at'=>'datetime','repertoire_revision_snapshot'=>'integer']; }
    public function service(): BelongsTo { return $this->belongsTo(Service::class); }
    public function songs(): HasMany { return $this->hasMany(SongExecution::class)->orderBy('position'); }
    public function participants(): HasMany { return $this->hasMany(ServiceParticipant::class); }
    public function ministers(): HasMany { return $this->hasMany(ServiceExecutionMinister::class); }
}
