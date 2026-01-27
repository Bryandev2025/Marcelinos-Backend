<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Booking extends Model
{
    protected $fillable = [
        'guest_id',
        'reference_number',
        'room_id',
        'venue_id',
        'check_in',
        'check_out',
        'total_price',
        'status',
        'no_of_days',
    ];

    protected $casts = [
        'check_in'    => 'datetime',
        'check_out'   => 'datetime',
        'total_price' => 'decimal:2',
    ];

        protected static function booted()
    {

        static::saved(function (Booking $booking) {

            if (! $booking->room) return;

            // If booking is occupied -> room must be occupied
            if ($booking->status === 'occupied') {
                $booking->room->update(['status' => 'occupied']);
            }

            // If booking is completed or cancelled -> free the room
            if (in_array($booking->status, ['completed', 'cancelled'])) {
                $booking->room->update(['status' => 'available']);
            }
        });


        static::creating(function ($booking) {
            $booking->reference_number = 'MWA-' . now()->year . '-' . str_pad(rand(1,999999),6,'0',STR_PAD_LEFT);
        });

        
    }

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