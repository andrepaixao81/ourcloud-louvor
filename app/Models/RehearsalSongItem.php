<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RehearsalSongItem extends BaseModel
{
    protected function casts(): array
    {
        return ['priority' => 'integer', 'resolved_at' => 'datetime'];
    }

    public function song(): BelongsTo { return $this->belongsTo(Song::class)->withTrashed(); }
    public function addedBy(): BelongsTo { return $this->belongsTo(User::class, 'added_by'); }
    public function resolvedBy(): BelongsTo { return $this->belongsTo(User::class, 'resolved_by'); }
}
