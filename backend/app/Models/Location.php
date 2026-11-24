<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Location extends Model
{
    protected $fillable = [
        'member_id',
        'latitude',
        'longitude',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(
            RoomMember::class,
            'member_id'
        );
    }
}
