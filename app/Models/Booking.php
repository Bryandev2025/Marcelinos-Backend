<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Jobs\SendBookingConfirmation;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'guest_id',
        'reference_number',
        'qr_code',
        'check_in',
        'check_out',
        'total_price',
        'status',
        'no_of_days',
        'testimonial_feedback_sent_at',
    ];

    protected $casts = [
        'check_in'    => 'datetime',
        'check_out'   => 'datetime',
        'total_price' => 'decimal:2',
        'no_of_days'  => 'integer',
        'testimonial_feedback_sent_at' => 'datetime',
    ];

    protected static function booted()
    {
        /**
         * Generate reference number before create
         */
        static::creating(function ($booking) {
            $booking->reference_number =
                'MWA-' . now()->year . '-' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
        });

        /**
         * Generate QR code AFTER booking is created
         */
        static::created(function (Booking $booking) {

            $qrData = json_encode([
                'booking_id' => $booking->id,
                'reference'  => $booking->reference_number,
                'guest_id'   => $booking->guest_id,
            ]);

            $path = 'qr/bookings/' . Str::uuid() . '.svg';

            Storage::disk('public')->put(
                $path,
                QrCode::size(300)->generate($qrData)
            );

            // Prevent infinite event loop
            $booking->updateQuietly([
                'qr_code' => $path,
            ]);

            SendBookingConfirmation::dispatch($booking);
        });

        /**
         * Existing room status logic (REMOVED - redundant)
         */
        // static::saved(function (Booking $booking) {
        //     $rooms = $booking->rooms;

        //     if ($rooms->isEmpty()) {
        //         return;
        //     }

        //     if ($booking->status === self::STATUS_OCCUPIED) {
        //         $rooms->each->update(['status' => 'occupied']);
        //     }

        //     if (in_array($booking->status, [
        //         self::STATUS_COMPLETED,
        //         self::STATUS_CANCELLED
        //     ])) {
        //         $rooms->each->update(['status' => 'available']);
        //     }
        // });
    }

    /* ================= RELATIONSHIPS ================= */

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

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    /* ================= STATUSES ================= */

    const STATUS_UNPAID    = 'unpaid';
    const STATUS_CONFIRMED  = 'confirmed';
    const STATUS_OCCUPIED   = 'occupied';
    const STATUS_COMPLETED  = 'completed';
    const STATUS_PAID       = 'paid';
    const STATUS_CANCELLED  = 'cancelled';
    const STATUS_RESCHEDULE = 'reschedule';

    public static function statusOptions(): array
    {
        return [
            self::STATUS_UNPAID => 'Unpaid',
            self::STATUS_CONFIRMED => 'Confirmed',
            self::STATUS_OCCUPIED => 'Occupied',
            self::STATUS_PAID => 'Paid',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    public static function statusColors(): array
    {
        return [
            'primary' => self::STATUS_UNPAID,
            'success' => self::STATUS_CONFIRMED,
            'warning' => self::STATUS_OCCUPIED,
            'secondary' => self::STATUS_COMPLETED,
            'info' => self::STATUS_PAID,
            'danger' => self::STATUS_CANCELLED,
        ];
    }
}
