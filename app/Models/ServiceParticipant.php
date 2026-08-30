<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceParticipant extends BaseModel
{
    use SoftDeletes;
    public function person(): BelongsTo { return $this->belongsTo(Person::class); }
    public function serviceExecution(): BelongsTo { return $this->belongsTo(ServiceExecution::class); }
    public function roles(): BelongsToMany { return $this->belongsToMany(MinistryRole::class, 'service_participant_roles', 'service_participant_id', 'ministry_role_id')->withTimestamps(); }
}
