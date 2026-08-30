<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Repertoire extends BaseModel
{
    use SoftDeletes;
    protected function casts(): array { return ['published_at'=>'datetime','locked_at'=>'datetime','announced_at'=>'datetime','announced_revision'=>'integer']; }
    public function service(): BelongsTo { return $this->belongsTo(Service::class); }
    public function openingSong(): BelongsTo { return $this->belongsTo(Song::class, 'opening_song_id')->withTrashed(); }
    public function openingSongVersion(): BelongsTo { return $this->belongsTo(SongVersion::class, 'opening_song_version_id'); }
    public function openingMinister(): BelongsTo { return $this->belongsTo(Person::class, 'opening_minister_person_id'); }
    public function items(): HasMany { return $this->hasMany(RepertoireItem::class)->orderBy('position'); }
}
