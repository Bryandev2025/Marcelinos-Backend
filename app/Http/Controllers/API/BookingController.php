<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Room;
use App\Models\Venue;
use App\Models\Guest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    //
    /**
     * Display all bookings
     */
    public function index()
    {
        try {
            if (Booking::count() === 0) {
                return response()->json(['message' => 'No bookings found'], 404);
            }

            $bookings = Booking::with(['guest', 'rooms', 'venues'])->get();
            return response()->json($bookings, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving bookings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function showByReference(string $reference)
    {
        try {
            $booking = Booking::with(['guest', 'rooms', 'venues'])
                ->where('reference_number', $reference)
                ->first();

            if (!$booking) {
                return response()->json(['message' => 'Receipt not found'], 404);
            }

            // Convert check_in/check_out to Carbon safely
            $check_in = Carbon::parse($booking->check_in);
            $check_out = Carbon::parse($booking->check_out);
            $issued_on = Carbon::parse($booking->created_at);

            return response()->json([
                'reference_number' => $booking->reference_number,
                'created_at' => $booking->created_at->format('M d, Y'),
                'booking_status' => $booking->status,
                'check_in' => $check_in->format('M d, Y'),
                'check_out' => $check_out->format('M d, Y'),
                'issued_on' => $issued_on->format('M d, Y'),
                'nights' => $booking->no_of_days,
                'guest_name' => $booking->guest->last_name . ' ' . $booking->guest->first_name,
                'rooms' => $booking->rooms->map(fn ($room) => [
                    'name' => $room->name,
                    'type' => $room->type,
                    'capacity' => $room->capacity,
                    'price' => $room->price,
                ])->all(),
                'venues' => $booking->venues->map(fn ($venue) => [
                    'name' => $venue->name,
                    'capacity' => $venue->capacity,
                    'price' => $venue->price,
                ])->all(),
                'subtotal' => $booking->total_price,
                'grand_total' => $booking->total_price,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to load receipt',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display a booking by reference number (frontend QR lookup)
     */
    public function showByReferenceNumber(string $reference)
    {
        try {
            $booking = Booking::with(['guest', 'rooms', 'venues'])
                ->where('reference_number', $reference)
                ->first();

            if (!$booking) {
                return response()->json(['message' => 'Booking not found'], 404);
            }

            $hasTestimonial = $booking->reviews()
                ->where('is_site_review', true)
                ->exists();

            return response()->json([
                'booking' => $booking,
                'qr_code_url' => $booking->qr_code
                    ? Storage::disk('public')->url($booking->qr_code)
                    : null,
                'has_testimonial' => $hasTestimonial,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving booking',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    public function store(Request $request)
    {
        $validated = $request->validate(
            [
                'reference_number' => 'nullable|string', // optional; server auto-generates if not provided
                'rooms'   => 'required|array|min:1',
                'rooms.*' => ['required', 'integer', 'distinct', Rule::exists('rooms', 'id')],
                'venues'  => 'nullable|array',
                'venues.*' => ['required_with:venues', 'integer', 'distinct', Rule::exists('venues', 'id')],
                'check_in'  => 'required|string',
                'check_out' => 'required|string',
                'days'      => 'required|integer|min:1',
                'total_price' => 'required|numeric|min:0',
            ],
            [
                'rooms.*.exists' => 'Selected room :input does not exist.',
                'rooms.*.distinct' => 'Duplicate room selection is not allowed.',
                'venues.*.exists' => 'Selected venue :input does not exist.',
            ]
        );

        try {
            $checkIn  = Carbon::createFromFormat('M d, Y', $validated['check_in'])->startOfDay();
            $checkOut = Carbon::createFromFormat('M d, Y', $validated['check_out'])->endOfDay();
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Invalid date format',
                'error'   => 'Expected format: Jan 20, 2026'
            ], 422);
        }

        // Logical date validation
        if ($checkOut->lt($checkIn)) {
            return response()->json([
                'message' => 'Invalid date range',
                'error'   => 'Check-out cannot be before check-in',
            ], 422);
        }

        // Store Guest first
        $guest = Guest::store($request);

        $roomIds = collect($validated['rooms'])
            ->map(function ($room) {
                if (is_array($room)) {
                    return $room['id'] ?? ($room[0] ?? null);
                }
                return $room;
            })
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $venueIds = isset($validated['venues']) ? collect($validated['venues'])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all() : [];

        // Fail early if any provided room does not actually exist
        $existingRoomIds = Room::whereIn('id', $roomIds)->pluck('id')->all();
        if (count($existingRoomIds) !== count($roomIds)) {
            return response()->json([
                'message' => 'One or more selected rooms do not exist',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Prevent booking conflict: no double-booking within date range
        |--------------------------------------------------------------------------
        */
        $availableRoomIds = Room::whereIn('id', $roomIds)
            ->availableBetween($checkIn, $checkOut)
            ->pluck('id')
            ->all();
        $conflictingRoomIds = array_values(array_diff($roomIds, $availableRoomIds));

        if (!empty($conflictingRoomIds)) {
            $conflictingRooms = Room::whereIn('id', $conflictingRoomIds)->get(['id', 'name']);
            return response()->json([
                'message' => 'Booking conflict: one or more rooms are already booked for the selected dates.',
                'error'   => 'date_range_conflict',
                'conflicts' => [
                    'rooms' => $conflictingRooms->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])->values()->all(),
                ],
            ], 422);
        }

        if (!empty($venueIds)) {
            $availableVenueIds = Venue::whereIn('id', $venueIds)
                ->availableBetween($checkIn, $checkOut)
                ->pluck('id')
                ->all();
            $conflictingVenueIds = array_values(array_diff($venueIds, $availableVenueIds));

            if (!empty($conflictingVenueIds)) {
                $conflictingVenues = Venue::whereIn('id', $conflictingVenueIds)->get(['id', 'name']);
                return response()->json([
                    'message' => 'Booking conflict: one or more venues are already booked for the selected dates.',
                    'error'   => 'date_range_conflict',
                    'conflicts' => [
                        'venues' => $conflictingVenues->map(fn ($v) => ['id' => $v->id, 'name' => $v->name])->values()->all(),
                    ],
                ], 422);
            }
        }

        // Single booking row; attach multiple rooms and venues
        $booking = Booking::create([
            'guest_id'          => $guest->id,
            'reference_number'  => $validated['reference_number'] ?? null, // model auto-generates if null
            'check_in'          => $checkIn,
            'check_out'         => $checkOut,
            'no_of_days'        => $validated['days'],
            'total_price'       => $validated['total_price'],
            'status'            => 'pending',
        ]);

        $booking->rooms()->attach($roomIds);
        if (!empty($venueIds)) {
            $booking->venues()->attach($venueIds);
        }

        $booking->load(['guest', 'rooms', 'venues']);

        return response()->json([
            'message' => 'Booking created successfully',
            'guest'   => $guest,
            'booking' => $booking,
            'total_price' => $validated['total_price'],
        ], 201);
    }

    /**
     * Display a specific booking
     */
    public function show($id)
    {
        try {
            $booking = Booking::with(['guest', 'rooms', 'venues'])->find($id);

            if (!$booking) {
                return response()->json(['message' => 'Booking not found'], 404);
            }

            return response()->json($booking, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving booking',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // public function update(Request $request, Booking $booking)
    // {
    //     try {
    //         $validated = $request->validate([
    //             'guest_id' => [
    //                 'sometimes',
    //                 'required',
    //                 Rule::exists('guests', 'id')
    //             ],
    //             'room_id' => [
    //                 'sometimes',
    //                 'required',
    //                 Rule::exists('rooms', 'id')
    //             ],
    //             'check_in' => 'sometimes|required|date',
    //             'check_out' => 'sometimes|required|date',
    //             'status' => [
    //                 'sometimes',
    //                 Rule::in(['pending','confirmed','occupied','completed','cancelled'])
    //             ],
    //             'remarks' => 'sometimes|nullable|string|max:255'
    //         ]);

    //         /*
    //         |--------------------------------------------------------------------------
    //         | Validate date relationship
    //         |--------------------------------------------------------------------------
    //         */
    //         if (
    //             isset($validated['check_in']) ||
    //             isset($validated['check_out'])
    //         ) {
    //             $checkIn  = Carbon::parse($validated['check_in'] ?? $booking->check_in);
    //             $checkOut = Carbon::parse($validated['check_out'] ?? $booking->check_out);

    //             if ($checkOut->lessThanOrEqualTo($checkIn)) {
    //                 return response()->json([
    //                     'message' => 'Check-out must be after check-in.'
    //                 ], 422);
    //             }
    //         }

    //         /*
    //         |--------------------------------------------------------------------------
    //         | Handle cancellation rules
    //         |--------------------------------------------------------------------------
    //         */
    //         if (
    //             isset($validated['status']) &&
    //             $validated['status'] === 'cancelled'
    //         ) {
    //             if (!in_array($booking->status, ['pending', 'confirmed'])) {
    //                 return response()->json([
    //                     'message' => 'Only pending or confirmed bookings can be cancelled.'
    //                 ], 422);
    //             }
    //         }

    //         /*
    //         |--------------------------------------------------------------------------
    //         | Recalculate price if dates or room changed
    //         |--------------------------------------------------------------------------
    //         */
    //         if (
    //             isset($validated['check_in']) ||
    //             isset($validated['check_out']) ||
    //             isset($validated['room_id'])
    //         ) {
    //             $checkIn  = Carbon::parse($validated['check_in'] ?? $booking->check_in);
    //             $checkOut = Carbon::parse($validated['check_out'] ?? $booking->check_out);

    //             $nights = max(1, $checkOut->diffInDays($checkIn));

    //             $room = Room::findOrFail($validated['room_id'] ?? $booking->room_id);

    //             $validated['total_price'] = $room->price_per_night * $nights;
    //             $validated['num_of_days'] = $nights;
    //         }

    //         /*
    //         |--------------------------------------------------------------------------
    //         | Persist update
    //         |--------------------------------------------------------------------------
    //         */
    //         $booking->update($validated);

    //         return response()->json([
    //             'message' => 'Booking updated successfully.',
    //             'data' => $booking->load(['guest', 'room'])
    //         ], 200);
    //     } catch (\Illuminate\Validation\ValidationException $e) {
    //         return response()->json([
    //             'message' => 'Validation failed',
    //             'errors' => $e->errors()
    //         ], 422);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'message' => 'Error updating booking',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    public function destroy($id)
    {
        try {
            $booking = Booking::find($id);

            if (!$booking) {
                return response()->json(['message' => 'Booking not found'], 404);
            }

            $booking->delete();

            return response()->json(['message' => 'Booking deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error deleting booking',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function cancel(Request $request, Booking $booking)
    {
        try {
            if (!in_array($booking->status, ['pending', 'confirmed'])) {
                return response()->json([
                    'message' => 'Booking cannot be cancelled in its current state.'
                ], 422);
            }

            $booking->update([
                'status' => 'cancelled',
                'remarks' => $request->remarks
            ]);

            return response()->json([
                'message' => 'Booking cancelled successfully.',
                'booking' => $booking
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error cancelling booking',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}