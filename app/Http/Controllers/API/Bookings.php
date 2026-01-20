<?php

namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Room;
use Illuminate\Auth\CreatesUserProviders;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class Bookings extends Controller
{
    /**
     * Display all bookings
     */
    public function index()
    {
        try {
            if (Booking::count() === 0) {
                return response()->json(['message' => 'No bookings found'], 404);
            }
            
            $bookings = Booking::with(['guest', 'room'])->get();
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
            $booking = Booking::with(['guest', 'room'])
                ->where('reference_id', $reference)
                ->first();

            if (!$booking) {
                return response()->json(['message' => 'Receipt not found'], 404);
            }

            // Convert check_in/check_out to Carbon safely
            $check_in = Carbon::parse($booking->check_in);
            $check_out = Carbon::parse($booking->check_out);
            $issued_on = Carbon::parse($booking->created_at);

            return response()->json([
                'reference_id' => $booking->reference_id,
                'check_in' => $check_in->format('M d, Y'),
                'check_out' => $check_out->format('M d, Y'),
                'issued_on' => $issued_on->format('M d, Y'),
                'nights' => $check_in->diffInDays($check_out),
                'guest_name' => $booking->guest->last_name . ' ' . $booking->guest->first_name,
                'room' => [
                    'number' => $booking->room->room_number,
                    'type' => $booking->room->type,
                    'capacity' => $booking->room->capacity,
                    'price' => $booking->total_price,
                ],
                'subtotal' => $booking->total_price,
                'grand_total' => $booking->total_price,
                'payment_method' => $booking->payment_method,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to load receipt',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    public function store(Request $request)
    {
        $validated = $request->validate([
            'guest_id'  => 'required|exists:guests,id',
            'reference_id' => 'required|string',
            'room_id'   => 'required|exists:rooms,id',
            'check_in'  => 'required|string',
            'check_out' => 'required|string',
        ]);

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
        if ($checkOut->lte($checkIn)) {
            return response()->json([
                'message' => 'Invalid date range',
                'error'   => 'Check-out must be after check-in'
            ], 422);
        }

        // Calculate nights
        $nights = max(1, $checkOut->diffInDays($checkIn));

        $room = Room::findOrFail($validated['room_id']);
        $totalPrice = $room->price_per_night * $nights;

        $booking = Booking::create([
            'guest_id'    => $validated['guest_id'],
            'reference_id' => $validated['reference_id'],
            'room_id'     => $validated['room_id'],
            'check_in'    => $checkIn,
            'check_out'   => $checkOut,
            'total_price' => $totalPrice,
            'status'      => 'pending',
        ]);

        return response()->json([
            'message' => 'Booking created successfully',
            'data'    => $booking
        ], 201);
    }

    /**
     * Display a specific booking
     */
    public function show($id)
    {
        try {
            $booking = Booking::with(['guest', 'room'])->find($id);

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

    public function update(Request $request, Booking $booking)
    {
        try {
            $validated = $request->validate([
                'guest_id' => [
                    'sometimes',
                    'required',
                    Rule::exists('guests', 'id')
                ],
                'room_id' => [
                    'sometimes',
                    'required',
                    Rule::exists('rooms', 'id')
                ],
                'check_in' => 'sometimes|required|date',
                'check_out' => 'sometimes|required|date',
                'status' => [
                    'sometimes',
                    Rule::in(['pending','confirmed','occupied','completed','cancelled'])
                ],
                'remarks' => 'sometimes|nullable|string|max:255'
            ]);

            /*
            |--------------------------------------------------------------------------
            | Validate date relationship
            |--------------------------------------------------------------------------
            */
            if (
                isset($validated['check_in']) ||
                isset($validated['check_out'])
            ) {
                $checkIn  = Carbon::parse($validated['check_in'] ?? $booking->check_in);
                $checkOut = Carbon::parse($validated['check_out'] ?? $booking->check_out);

                if ($checkOut->lessThanOrEqualTo($checkIn)) {
                    return response()->json([
                        'message' => 'Check-out must be after check-in.'
                    ], 422);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Handle cancellation rules
            |--------------------------------------------------------------------------
            */
            if (
                isset($validated['status']) &&
                $validated['status'] === 'cancelled'
            ) {
                if (!in_array($booking->status, ['pending', 'confirmed'])) {
                    return response()->json([
                        'message' => 'Only pending or confirmed bookings can be cancelled.'
                    ], 422);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Recalculate price if dates or room changed
            |--------------------------------------------------------------------------
            */
            if (
                isset($validated['check_in']) ||
                isset($validated['check_out']) ||
                isset($validated['room_id'])
            ) {
                $checkIn  = Carbon::parse($validated['check_in'] ?? $booking->check_in);
                $checkOut = Carbon::parse($validated['check_out'] ?? $booking->check_out);

                $nights = max(1, $checkOut->diffInDays($checkIn));

                $room = Room::findOrFail($validated['room_id'] ?? $booking->room_id);

                $validated['total_price'] = $room->price_per_night * $nights;
                $validated['num_of_days'] = $nights;
            }

            /*
            |--------------------------------------------------------------------------
            | Persist update
            |--------------------------------------------------------------------------
            */
            $booking->update($validated);

            return response()->json([
                'message' => 'Booking updated successfully.',
                'data' => $booking->load(['guest', 'room'])
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error updating booking',
                'error' => $e->getMessage()
            ], 500);
        }
    }

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
