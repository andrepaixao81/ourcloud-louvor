<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MinistryRole extends BaseModel
{
    use SoftDeletes;
    protected function casts(): array { return ['is_minister_role'=>'boolean','active'=>'boolean']; }
    public function people(): BelongsToMany { return $this->belongsToMany(Person::class, 'person_ministry_role'); }
}
