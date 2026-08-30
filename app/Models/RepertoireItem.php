<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RepertoireItem extends BaseModel
{
    use SoftDeletes;
    public function repertoire(): BelongsTo { return $this->belongsTo(Repertoire::class); }
    public function song(): BelongsTo { return $this->belongsTo(Song::class)->withTrashed(); }
    public function songVersion(): BelongsTo { return $this->belongsTo(SongVersion::class); }
    public function minister(): BelongsTo { return $this->belongsTo(Person::class, 'minister_person_id'); }
}
