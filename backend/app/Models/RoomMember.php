<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RoomMember extends Model
{
    protected $fillable = [
        'room_id',
        'nickname',
        'session_token_hash',
        'role',
        'status',
        'joined_at',
    ];

    protected $hidden = [
        'session_token_hash',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function location(): HasOne
    {
        return $this->hasOne(Location::class, 'member_id');
    }
}
