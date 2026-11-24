<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Room extends Model
{
    protected $fillable = [
        'name',
        'pin',
        'host_member_id',
        'travel_mode',
        'status',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function members(): HasMany
    {
        return $this->hasMany(RoomMember::class);
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(
            RoomMember::class,
            'host_member_id'
        );
    }

    public function meetingResults(): HasMany
    {
        return $this->hasMany(MeetingResult::class);
    }
}
