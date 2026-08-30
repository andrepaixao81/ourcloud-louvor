<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Song extends BaseModel
{
    use SoftDeletes;
    protected function casts(): array { return ['approved_at'=>'datetime']; }
    public function category(): BelongsTo { return $this->belongsTo(SongCategory::class, 'category_id'); }
    public function versions(): HasMany { return $this->hasMany(SongVersion::class); }
    public function executions(): HasMany { return $this->hasMany(SongExecution::class); }
    public function ministerKeys(): HasMany { return $this->hasMany(MinisterSongKey::class); }
}
