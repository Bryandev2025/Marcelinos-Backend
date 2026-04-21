<?php

namespace App\Models;

use App\Mail\BookingCreated;
use App\Mail\TestimonialFeedbackEmail;
use App\Support\RoomInventoryGroupKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'guest_id',
        'reference_number',
        'receipt_token',
        'qr_code',
        'check_in',
        'check_out',
        'total_price',
        'status',
        'payment_method',
        'online_payment_plan',
        'xendit_invoice_id',
        'xendit_invoice_url',
        'no_of_days',
        'venue_event_type',
        'reminder_sent',
        'reminder_sent_at',
        'reminder_sms_sent',
        'reminder_sms_sent_at',
        'reminder_sms_error',
        'testimonial_feedback_sent_at',
    ];

    protected $casts = [
        'check_in' => 'datetime',
        'check_out' => 'datetime',
        'total_price' => 'decimal:2',
        'no_of_days' => 'integer',
        'reminder_sent' => 'boolean',
        'reminder_sent_at' => 'datetime',
        'reminder_sms_sent' => 'boolean',
        'reminder_sms_sent_at' => 'datetime',
        'testimonial_feedback_sent_at' => 'datetime',
    ];

    protected static function booted()
    {
        /**
         * Generate reference number before create
         */
        static::creating(function ($booking) {
            $booking->reference_number =
                'MWA-'.now()->year.'-'.str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
            if (! Str::isUuid((string) $booking->receipt_token)) {
                $booking->receipt_token = (string) Str::uuid();
            }
        });

        /**
         * Handle actions AFTER booking is created
         */
        static::created(function (Booking $booking) {
            $booking->generateQrCode();

            $booking->loadMissing('guest');
            if ($booking->guest && $booking->guest->email) {
                $mail = Mail::to($booking->guest->email);
                $bookingCcAddress = config('mail.booking_cc_address');

                if (filled($bookingCcAddress)) {
                    $mail->cc($bookingCcAddress);
                }

                $mail->send(new BookingCreated($booking));
            }
        });

        /**
         * Send testimonial feedback email when status transitions to completed (scheduler or admin).
         */
        static::updated(function (Booking $booking) {
            if (! $booking->wasChanged('status') || $booking->status !== Booking::STATUS_COMPLETED) {
                return;
            }
            if ($booking->testimonial_feedback_sent_at !== null) {
                return;
            }

            $booking->loadMissing('guest');
            $email = $booking->guest?->email;
            if (! $email) {
                return;
            }

            try {
                Mail::to($email)->send(new TestimonialFeedbackEmail($booking));
            } catch (\Throwable $e) {
                Log::error('Failed sending testimonial feedback', [
                    'booking_id' => $booking->id,
                    'reference_number' => $booking->reference_number,
                    'guest_email' => $email,
                    'error' => $e->getMessage(),
                ]);

                return;
            }

            $booking->updateQuietly(['testimonial_feedback_sent_at' => now()]);
        });
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

    /**
     * Guest-selected room type + bed-spec lines (no specific room until staff assigns).
     */
    public function roomLines()
    {
        return $this->hasMany(BookingRoomLine::class);
    }

    /**
     * Ensures assigned physical rooms exactly fulfill each requested type + bed-spec line (billing statement).
     *
     * @param  array<int|string>  $roomIds
     *
     * @throws ValidationException
     */
    public static function validateAssignedRoomsFulfillRoomLines(Booking $booking, array $roomIds): void
    {
        $booking->loadMissing('roomLines');
        if ($booking->roomLines->isEmpty()) {
            return;
        }

        $roomIds = array_values(array_unique(array_filter(array_map('intval', $roomIds))));
        $expectedTotal = (int) $booking->roomLines->sum('quantity');

        $rooms = Room::query()
            ->whereIn('id', $roomIds)
            ->with(['bedSpecifications'])
            ->get();

        if (count($rooms) !== count($roomIds)) {
            throw ValidationException::withMessages([
                'rooms' => ['One or more selected rooms are invalid or missing.'],
            ]);
        }

        if (count($rooms) !== $expectedTotal) {
            throw ValidationException::withMessages([
                'rooms' => ["Assign exactly {$expectedTotal} physical room(s) to match the guest billing ({$expectedTotal} slot(s) requested)."],
            ]);
        }

        foreach ($booking->roomLines->groupBy(fn (BookingRoomLine $l) => $l->room_type."\0".$l->inventory_group_key) as $group) {
            $line = $group->first();
            $need = (int) $group->sum('quantity');
            $have = $rooms->filter(function (Room $room) use ($line) {
                return $room->type === $line->room_type
                    && RoomInventoryGroupKey::forRoom($room) === $line->inventory_group_key;
            })->count();

            if ($have !== $need) {
                $label = $line->displayLabel();
                throw ValidationException::withMessages([
                    'rooms' => ["Guest requested {$need} × {$label}. You assigned {$have} matching room(s)."],
                ]);
            }
        }
    }

    /**
     * Guest billing includes room lines → staff must assign matching physical rooms before check-in.
     */
    public function expectsRoomAssignments(): bool
    {
        if (! $this->relationLoaded('roomLines')) {
            if (! $this->exists) {
                return false;
            }
            $this->loadMissing('roomLines');
        }

        return $this->roomLines->isNotEmpty();
    }

    /**
     * Booking was sold with a venue package (see API create / Filament) → at least one venue must stay attached.
     */
    public function expectsVenueAssignments(): bool
    {
        return filled($this->venue_event_type);
    }

    /**
     * Whether rooms (if required) and venues (if required) satisfy rules for transitioning to {@see STATUS_OCCUPIED}.
     */
    public function assignmentsSatisfiedForOccupied(): bool
    {
        try {
            $this->assertAssignmentsSatisfiedForOccupied();

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    /**
     * Ensures physical rooms match room lines and venue package has at least one venue before marking occupied.
     *
     * @throws ValidationException
     */
    public function assertAssignmentsSatisfiedForOccupied(): void
    {
        if (! $this->relationLoaded('roomLines') && $this->exists) {
            $this->loadMissing('roomLines');
        }
        if (! $this->relationLoaded('venues')) {
            if ($this->exists) {
                $this->loadMissing('venues');
            } else {
                $this->setRelation('venues', collect());
            }
        }
        if (! $this->relationLoaded('rooms')) {
            if ($this->exists) {
                $this->loadMissing(['rooms.bedSpecifications']);
            } else {
                $this->setRelation('rooms', collect());
            }
        } elseif ($this->rooms instanceof Collection) {
            $this->rooms->loadMissing('bedSpecifications');
        }

        if ($this->expectsRoomAssignments()) {
            $roomIds = $this->rooms->pluck('id')->all();
            self::validateAssignedRoomsFulfillRoomLines($this, $roomIds);
        }

        if ($this->expectsVenueAssignments() && $this->venues->isEmpty()) {
            throw ValidationException::withMessages([
                'venues' => ['Assign at least one venue before check-in.'],
            ]);
        }
    }

    public function venues()
    {
        return $this->belongsToMany(Venue::class, 'booking_venue')->withTimestamps();
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /* ================= STATUSES ================= */

    const STATUS_UNPAID = 'unpaid';

    const STATUS_PARTIAL = 'partial';

    const STATUS_OCCUPIED = 'occupied';

    const STATUS_COMPLETED = 'completed';

    const STATUS_PAID = 'paid';

    const STATUS_CANCELLED = 'cancelled';

    const STATUS_RESCHEDULED = 'rescheduled';

    const UNPAID_EXPIRY_DAYS = 3;

    /**
     * Show the billing-statement 3-day down payment notice only when check-in is
     * at least this many calendar days after the booking date (day granularity).
     * Same-day / next-day / short-lead bookings use other payment arrangements.
     */
    const DOWN_PAYMENT_NOTICE_MIN_LEAD_DAYS = 4;

    /** Unpaid settlement / auto-cancel deadline on the check-in calendar day (Asia/Manila). */
    const CHECK_IN_UNPAID_SETTLEMENT_HOUR = 21;

    public static function timezoneManila(): string
    {
        return 'Asia/Manila';
    }

    /**
     * Check-in calendar date in Manila is strictly after "today" in Manila (receipt: messenger instructions).
     */
    public function isCheckInStrictlyAfterTodayManila(?Carbon $at = null): bool
    {
        if (! $this->check_in) {
            return false;
        }
        $at = $at ?? now();
        $tz = self::timezoneManila();
        $checkInDay = $this->check_in->copy()->timezone($tz)->startOfDay();
        $today = $at->copy()->timezone($tz)->startOfDay();

        return $checkInDay->gt($today);
    }

    /**
     * Whether the billing statement should show Messenger settlement (30% deposit via Messenger).
     *
     * Used when check-in calendar date in Manila is strictly after "today" — same unified 9:00 PM check-in-day
     * deadline still applies for unpaid auto-cancel and {@see unpaidExpiresAt}.
     */
    public function useMessengerDepositInstructions(?Carbon $at = null): bool
    {
        return $this->isCheckInStrictlyAfterTodayManila($at);
    }

    /**
     * 9:00 PM Asia/Manila unpaid settlement deadline:
     * - check-in is on/earlier than booking day: 9:00 PM on check-in day
     * - check-in is after booking day: 9:00 PM on the day after booking day
     */
    public function unpaidSettlementDeadlineManila(?Carbon $at = null): ?Carbon
    {
        if (! $this->check_in) {
            return null;
        }
        $tz = self::timezoneManila();
        $anchor = ($this->created_at ?? $at ?? now())->copy()->timezone($tz);
        $checkInDay = $this->check_in->copy()->timezone($tz)->startOfDay();
        $bookingDay = $anchor->copy()->startOfDay();
        $targetDay = $checkInDay->gt($bookingDay)
            ? $bookingDay->copy()->addDay()
            : $checkInDay;

        return $targetDay->setTime(
            self::CHECK_IN_UNPAID_SETTLEMENT_HOUR,
            0,
            0,
        );
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_UNPAID => 'Unpaid',
            self::STATUS_PARTIAL => 'Partial',
            self::STATUS_OCCUPIED => 'Occupied',
            self::STATUS_PAID => 'Paid',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_RESCHEDULED => 'Rescheduled',
        ];
    }

    /**
     * Payment settlement deadline on the receipt: 9:00 PM Asia/Manila on the check-in calendar day
     * (same moment as unpaid auto-cancel). Null when the booking has no check-in datetime.
     *
     * @param  int|null  $days  Ignored; retained for call-site compatibility.
     */
    public function unpaidExpiresAt(?int $days = null): ?Carbon
    {
        return $this->unpaidSettlementDeadlineManila();
    }

    /**
     * Whether the receipt should show the 3-day down payment policy
     * (advance bookings only — not instant or next-day stays).
     */
    public function downPaymentNoticeApplies(): bool
    {
        if (! $this->check_in || ! $this->created_at) {
            return false;
        }

        $checkInDay = $this->check_in->copy()->startOfDay();
        $createdDay = $this->created_at->copy()->startOfDay();

        if ($checkInDay->lt($createdDay)) {
            return false;
        }

        $leadDays = $createdDay->diffInDays($checkInDay);

        return $leadDays >= self::DOWN_PAYMENT_NOTICE_MIN_LEAD_DAYS;
    }

    /**
     * True when booking is still unpaid and the evaluation time is at or after 9:00 PM (Manila)
     * on the check-in calendar day.
     *
     * @param  int|null  $days  Ignored; retained for call-site compatibility.
     */
    public function isExpiredUnpaid(?Carbon $at = null, ?int $days = null): bool
    {
        if ($this->status !== self::STATUS_UNPAID) {
            return false;
        }

        if (! $this->check_in) {
            return false;
        }

        $at = $at ?? now();
        $deadline = $this->unpaidSettlementDeadlineManila($at);

        return $deadline !== null && $at->gte($deadline);
    }

    /**
     * Cancel this booking if it exceeded the unpaid expiry window.
     * Returns true when status was changed.
     */
    public function expireIfUnpaidExceededRule(?Carbon $at = null, ?int $days = null): bool
    {
        if (! $this->isExpiredUnpaid($at, $days)) {
            return false;
        }

        return DB::transaction(function () use ($at, $days): bool {
            $fresh = self::query()->lockForUpdate()->find($this->id);
            if (! $fresh || ! $fresh->isExpiredUnpaid($at, $days)) {
                return false;
            }

            $fresh->update(['status' => self::STATUS_CANCELLED]);
            $this->refresh();

            return true;
        });
    }

    public static function statusColors(): array
    {
        return [
            'primary' => self::STATUS_UNPAID,
            'info' => self::STATUS_PARTIAL,
            'success' => self::STATUS_PAID,
            'warning' => self::STATUS_OCCUPIED,
            'secondary' => self::STATUS_COMPLETED,
            'danger' => self::STATUS_CANCELLED,
            'default' => self::STATUS_RESCHEDULED,
        ];
    }

    /* ================= BLOCKED DATE CONFLICTS ================= */

    /**
     * Scope: bookings that overlap a given date (any part of that day).
     * Excludes cancelled (and optionally completed) so staff see active bookings.
     */
    public function scopeOverlappingDate($query, $date): Builder
    {
        $date = Carbon::parse($date);
        $dateStart = $date->copy()->startOfDay();
        $dateEnd = $date->copy()->endOfDay();

        return $query
            ->whereNotIn('status', [self::STATUS_CANCELLED])
            ->where('check_in', '<=', $dateEnd)
            ->where('check_out', '>', $dateStart);
    }

    /**
     * Scope: bookings that occupy the lodging night for a calendar date (checkout day excluded).
     * Matches the room calendar: check-in Mar 29, check-out Mar 30 morning counts only Mar 29.
     */
    public function scopeOverlappingLodgingNight($query, $date): Builder
    {
        $d = Carbon::parse($date)->toDateString();

        return $query
            ->whereNotIn('status', [self::STATUS_CANCELLED])
            ->whereDate('check_in', '<=', $d)
            ->whereDate('check_out', '>', $d);
    }

    /**
     * Get bookings overlapping a date, with guest and assignable names for display.
     * Used by blocked-dates flow to show "contact customer first" info.
     *
     * @return array<int, array{id: int, reference_number: string, guest_name: string, email: string, contact_num: string, rooms: string, venues: string, check_in: string, check_out: string, status: string}>
     */
    public static function getConflictsForDate($date): array
    {
        $bookings = self::overlappingDate($date)
            ->with(['guest', 'rooms', 'venues'])
            ->orderBy('check_in')
            ->get();

        return $bookings->map(function (Booking $b) {
            return [
                'id' => $b->id,
                'reference_number' => $b->reference_number,
                'guest_name' => $b->guest?->full_name ?? '—',
                'email' => $b->guest?->email ?? '—',
                'contact_num' => $b->guest?->contact_num ?? '—',
                'rooms' => $b->rooms->pluck('name')->join(', ') ?: '—',
                'venues' => $b->venues->pluck('name')->join(', ') ?: '—',
                'check_in' => $b->check_in?->format('M j, Y g:i A') ?? '—',
                'check_out' => $b->check_out?->format('M j, Y g:i A') ?? '—',
                'status' => $b->status,
            ];
        })->values()->all();
    }

    /**
     * Bookings overlapping a calendar day that include a given room (for staff block warnings).
     *
     * @return array<int, array{id: int, reference_number: string, guest_name: string, email: string, contact_num: string, rooms: string, venues: string, check_in: string, check_out: string, status: string}>
     */
    public static function getConflictsForRoomOnDate(int $roomId, $date): array
    {
        $bookings = self::overlappingDate($date)
            ->whereHas('rooms', fn ($q) => $q->where('rooms.id', $roomId))
            ->with(['guest', 'rooms', 'venues'])
            ->orderBy('check_in')
            ->get();

        return $bookings->map(function (Booking $b) {
            return [
                'id' => $b->id,
                'reference_number' => $b->reference_number,
                'guest_name' => $b->guest?->full_name ?? '—',
                'email' => $b->guest?->email ?? '—',
                'contact_num' => $b->guest?->contact_num ?? '—',
                'rooms' => $b->rooms->pluck('name')->join(', ') ?: '—',
                'venues' => $b->venues->pluck('name')->join(', ') ?: '—',
                'check_in' => $b->check_in?->format('M j, Y g:i A') ?? '—',
                'check_out' => $b->check_out?->format('M j, Y g:i A') ?? '—',
                'status' => $b->status,
            ];
        })->values()->all();
    }

    /**
     * Bookings overlapping a calendar day that include a given venue (for staff block warnings).
     *
     * @return array<int, array{id: int, reference_number: string, guest_name: string, email: string, contact_num: string, rooms: string, venues: string, check_in: string, check_out: string, status: string}>
     */
    public static function getConflictsForVenueOnDate(int $venueId, $date): array
    {
        $bookings = self::overlappingDate($date)
            ->whereHas('venues', fn ($q) => $q->where('venues.id', $venueId))
            ->with(['guest', 'rooms', 'venues'])
            ->orderBy('check_in')
            ->get();

        return $bookings->map(function (Booking $b) {
            return [
                'id' => $b->id,
                'reference_number' => $b->reference_number,
                'guest_name' => $b->guest?->full_name ?? '—',
                'email' => $b->guest?->email ?? '—',
                'contact_num' => $b->guest?->contact_num ?? '—',
                'rooms' => $b->rooms->pluck('name')->join(', ') ?: '—',
                'venues' => $b->venues->pluck('name')->join(', ') ?: '—',
                'check_in' => $b->check_in?->format('M j, Y g:i A') ?? '—',
                'check_out' => $b->check_out?->format('M j, Y g:i A') ?? '—',
                'status' => $b->status,
            ];
        })->values()->all();
    }

    /* ================= PAYMENT HELPERS ================= */

    /**
     * Get the total amount paid so far for this booking.
     */
    public function getTotalPaidAttribute(): int|float
    {
        return $this->payments()->sum('partial_amount');
    }

    /**
     * Get the remaining balance for this booking.
     */
    public function getBalanceAttribute(): int|float
    {
        return max(0, $this->total_price - $this->total_paid);
    }

    /**
     * Generate and save QR Code for the booking.
     */
    public function generateQrCode(): void
    {
        if (! empty($this->qr_code)) {
            // Older QR files may exist but not be valid SVG. If the stored file doesn't
            // look like an SVG, regenerate it.
            if (Storage::disk('public')->exists($this->qr_code)) {
                $existing = (string) Storage::disk('public')->get($this->qr_code);
                if (str_contains($existing, '<svg')) {
                    return;
                }
            } else {
                return;
            }
        }

        $qrData = json_encode([
            // Keep both key names for backward compatibility with any existing scanners.
            'booking_id' => $this->id,
            'reference' => $this->reference_number,
            'reference_number' => $this->reference_number,
            'guest_id' => $this->guest_id,
        ]);

        $path = 'qr/bookings/'.Str::uuid().'.svg';

        Storage::disk('public')->put(
            $path,
            QrCode::format('svg')->size(300)->generate($qrData)
        );

        $this->updateQuietly([
            'qr_code' => $path,
        ]);
    }
}
