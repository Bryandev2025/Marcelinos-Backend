<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Booking extends Model
{
    protected $fillable = [
        'guest_id',
        'reference_number',
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
        'no_of_days' => 'integer',
    ];

        protected static function booted()
    {

        static::saved(function (Booking $booking) {
            $rooms = $booking->rooms;
            if ($rooms->isEmpty()) {
                return;
            }

            // If booking is occupied -> all rooms must be occupied
            if ($booking->status === 'occupied') {
                $rooms->each->update(['status' => 'occupied']);
            }

            // If booking is completed or cancelled -> free all rooms
            if (in_array($booking->status, ['completed', 'cancelled'])) {
                $rooms->each->update(['status' => 'available']);
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

    public function rooms()
    {
        return $this->belongsToMany(Room::class, 'booking_room')->withTimestamps();
    }

    public function venues()
    {
        return $this->belongsToMany(Venue::class, 'booking_venue')->withTimestamps();
    }

    // Optional but recommended
    const STATUS_PENDING    = 'pending';
    const STATUS_CONFIRMED  = 'confirmed';
    const STATUS_OCCUPIED   = 'occupied';
    const STATUS_COMPLETED  = 'completed';
    const STATUS_CANCELLED  = 'cancelled';
    const STATUS_RESCHEDULE = 'reschedule';
}