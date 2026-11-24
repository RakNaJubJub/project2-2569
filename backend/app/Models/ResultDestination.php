<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResultDestination extends Model
{
    protected $fillable = [
        'result_id',
        'name',
        'category',
        'latitude',
        'longitude',
        'total_time_seconds',
        'max_time_seconds',
        'distance_meters',
        'details',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'details' => 'array',
        ];
    }

    public function meetingResult(): BelongsTo
    {
        return $this->belongsTo(
            MeetingResult::class,
            'result_id'
        );
    }
}
