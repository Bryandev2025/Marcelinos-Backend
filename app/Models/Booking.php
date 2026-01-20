<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Booking extends Model
{
    protected $fillable = [
        'guest_id',
        'room_id',
        'venue_id',
        'check_in',
        'check_out',
        'total_price',
        'status',
        'payment_reference',
    ];

    protected $casts = [
        'check_in'    => 'datetime',
        'check_out'   => 'datetime',
        'total_price' => 'decimal:2',
    ];

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    // Optional but recommended
    const STATUS_PENDING    = 'pending';
    const STATUS_CONFIRMED  = 'confirmed';
    const STATUS_OCCUPIED   = 'occupied';
    const STATUS_COMPLETED  = 'completed';
    const STATUS_CANCELLED  = 'cancelled';
    const STATUS_RESCHEDULE = 'reschedule';
}
