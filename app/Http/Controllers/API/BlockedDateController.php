<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\BlockedDate;
use App\Models\Booking;
use App\Models\Room;
use App\Models\Venue;
use App\Support\ApiResponse;
use App\Support\RoomInventoryGroupAvailability;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class BlockedDateController extends Controller
{
    /**
     * Return all blocked dates as JSON.
     * Includes:
     * - Manually blocked dates from BlockedDate table
     * - Dates where all rooms or all venues are fully booked
     */
    public function index(Request $request): JsonResponse
    {
        $today = Carbon::today()->toDateString();
        $blockedDates = collect();
        $rescheduleHorizonDays = min(max((int) $request->query('horizon_days', 120), 30), 365);

        // 1. Get manually blocked dates
        $manualBlockedDates = BlockedDate::select('date', 'reason')
            ->whereDate('date', '>=', $today)
            ->get()
            ->map(fn($d) => [
                // Always Y-m-d so clients match calendar day keys (not ISO datetimes).
                'date' => Carbon::parse($d->date)->toDateString(),
                'reason' => $d->reason,
            ]);
        $blockedDates = $blockedDates->merge($manualBlockedDates);

        // 2. Get dates blocked by fully booked rooms/venues
        $bookingBlockedDates = $this->getBookingBlockedDates();
        $blockedDates = $blockedDates->merge($bookingBlockedDates);

        // Unique and sort by date
        $blockedDates = $blockedDates
            ->filter(fn($d) => isset($d['date']) && $d['date'] >= $today)
            ->unique(fn($d) => $d['date'])
            ->sortBy('date')
            ->values();

        // 3. Booking-specific conflicts for reschedule calendar
        $reference = $request->query('booking_reference');
        if ($reference) {
            $booking = Booking::with(['roomLines', 'venues'])
                ->where('reference_number', $reference)
                ->first();

            if ($booking) {
                $todayCarbon = Carbon::today();
                $horizonEnd = $todayCarbon->copy()->addDays($rescheduleHorizonDays);
                $bookingConflictedDates = [];

                $venueIds = $booking->venues->pluck('id')->all();
                $otherVenueBookings = collect();
                if (! empty($venueIds)) {
                    $otherVenueBookings = Booking::query()
                        ->with('venues:id')
                        ->where('id', '!=', $booking->id)
                        ->where('status', '!=', Booking::STATUS_CANCELLED)
                        ->where('check_in', '<', $horizonEnd)
                        ->where('check_out', '>', $todayCarbon)
                        ->whereHas('venues', fn($q) => $q->whereIn('venues.id', $venueIds))
                        ->get(['id', 'check_in', 'check_out']);
                }

                for ($i = 0; $i < $rescheduleHorizonDays; $i++) {
                    $dayStart = $todayCarbon->copy()->addDays($i)->startOfDay();
                    $dayEnd = $dayStart->copy()->addDay();
                    $hasConflict = false;

                    foreach ($booking->roomLines as $line) {
                        $remaining = RoomInventoryGroupAvailability::remainingForLine(
                            $line->room_type,
                            $line->inventory_group_key,
                            $dayStart,
                            $dayEnd,
                            $booking->id,
                        );

                        if ($remaining < (int) $line->quantity) {
                            $hasConflict = true;
                            break;
                        }
                    }

                    if (! $hasConflict && $otherVenueBookings->isNotEmpty()) {
                        foreach ($otherVenueBookings as $other) {
                            $otherStart = Carbon::parse($other->check_in);
                            $otherEnd = Carbon::parse($other->check_out);
                            if ($otherStart->lt($dayEnd) && $otherEnd->gt($dayStart)) {
                                $hasConflict = true;
                                break;
                            }
                        }
                    }

                    if ($hasConflict) {
                        $bookingConflictedDates[] = [
                            'date' => $dayStart->toDateString(),
                            'reason' => 'Fully booked',
                        ];
                    }
                }

                $blockedDates = $blockedDates
                    ->merge($bookingConflictedDates)
                    ->unique(fn($d) => $d['date'])
                    ->sortBy('date')
                    ->values();
            }
        }

        return ApiResponse::success($blockedDates->all());
    }

    /**
     * Get all dates blocked by paid or occupied bookings (capacity fully used)
     * where all rooms and/or all venues are booked.
     */
    private function getBookingBlockedDates(): array
    {
        return Cache::remember('api_booking_blocked_dates', now()->addMinutes(3), function () {
            $blockedDates = [];

            $totalRooms = Room::count();
            $totalVenues = Venue::count();
            $today = Carbon::today();
            $horizonEnd = $today->copy()->addDays(365);

            $bookings = Booking::with(['rooms:id', 'venues:id'])
                ->whereIn('status', [
                    Booking::STATUS_PAID,
                    Booking::STATUS_PARTIAL,
                    Booking::STATUS_OCCUPIED,
                ])
                ->where('check_in', '<', $horizonEnd)
                ->where('check_out', '>', $today)
                ->get(['id', 'check_in', 'check_out']);

            if ($bookings->isEmpty()) {
                return $blockedDates;
            }

            $roomCountPerDate = [];
            $venueCountPerDate = [];

            foreach ($bookings as $booking) {
                $checkIn = Carbon::parse($booking->check_in)->max($today);
                $checkOut = Carbon::parse($booking->check_out)->min($horizonEnd);

                if ($checkOut->lte($checkIn)) {
                    continue;
                }

                $dates = $this->getDateRange($checkIn, $checkOut);

                $bookedRoomCount = $booking->rooms->count();
                $bookedVenueCount = $booking->venues->count();

                foreach ($dates as $date) {
                    $roomCountPerDate[$date] = ($roomCountPerDate[$date] ?? 0) + $bookedRoomCount;
                    $venueCountPerDate[$date] = ($venueCountPerDate[$date] ?? 0) + $bookedVenueCount;
                }
            }

        // Add dates where all rooms are fully booked
            foreach ($roomCountPerDate as $date => $count) {
                if ($count >= $totalRooms) {
                    $blockedDates[] = [
                        'date' => $date,
                        'reason' => 'Fully booked',
                    ];
                }
            }

        // Add dates where all venues are fully booked
            foreach ($venueCountPerDate as $date => $count) {
                if ($count >= $totalVenues) {
                    $blockedDates[] = [
                        'date' => $date,
                        'reason' => 'Fully booked',
                    ];
                }
            }

            return $blockedDates;
        });
    }

    /**
     * Get all dates between check_in (inclusive) and check_out (exclusive).
     * Returns array of date strings in Y-m-d format.
     */
    private function getDateRange(Carbon $checkIn, Carbon $checkOut): array
    {
        $dates = [];
        $current = $checkIn->copy();

        while ($current->lt($checkOut)) {
            $dates[] = $current->format('Y-m-d');
            $current->addDay();
        }

        return $dates;
    }
}