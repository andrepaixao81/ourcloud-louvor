<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MinisterSongKey extends BaseModel
{
    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    public function person(): BelongsTo { return $this->belongsTo(Person::class); }
    public function song(): BelongsTo { return $this->belongsTo(Song::class); }
    public function lastService(): BelongsTo { return $this->belongsTo(Service::class, 'last_service_id'); }
    public function lastRepertoireItem(): BelongsTo { return $this->belongsTo(RepertoireItem::class, 'last_repertoire_item_id'); }
    public function updatedBy(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
}
