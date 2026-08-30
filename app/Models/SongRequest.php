<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SongRequest extends BaseModel
{
    protected function casts(): array { return ['reviewed_at'=>'datetime']; }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function requestedService(): BelongsTo { return $this->belongsTo(Service::class, 'requested_for_service_id'); }
    public function suggestedCategory(): BelongsTo { return $this->belongsTo(SongCategory::class, 'suggested_category_id'); }
    public function approvedSong(): BelongsTo { return $this->belongsTo(Song::class, 'approved_song_id'); }
}
