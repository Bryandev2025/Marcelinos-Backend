<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomChecklist extends Model
{
    protected $fillable = [
        'booking_id',
        'room_id',
        'generated_at',
        'completed_at',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RoomChecklistItem::class)->orderBy('sort_order');
    }
}

