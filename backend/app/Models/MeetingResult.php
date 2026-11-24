<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeetingResult extends Model
{
    protected $fillable = [
        'room_id',
        'algorithm',
        'snapshot_data',
        'result_data',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_data' => 'array',
            'result_data' => 'array',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function destinations(): HasMany
    {
        return $this->hasMany(ResultDestination::class, 'result_id');
    }
}
