<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\VenueResource;
use App\Models\Booking;
use App\Models\Venue;
use App\Models\VenueBlockedDate;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class VenueController extends Controller
{
    /**
     * List venues.
     * Same availability contract as RoomController: when check_in/check_out are provided,
     * All venues are returned but add availability status (available or not available).
     * - is_all=true: return all venues (e.g. homepage).
     * - Otherwise: require check_in & check_out; return venues with availability status based on the date range.
     * - is_block_date: true if this venue has a staff block overlapping [check_in, check_out]; null when is_all.
     * - When available is false: unavailability_code, unavailability_title, unavailability_detail (maintenance,
     *   venue marked booked, staff-blocked dates, or overlapping reservation).
     */
    public function index(Request $request)
    {
        try {
            $isAll = filter_var($request->query('is_all', false), FILTER_VALIDATE_BOOLEAN);
            $limit = min(max((int) $request->query('limit', $isAll ? 24 : 100), 1), 200);

            if (! $isAll) {
                $request->validate([
                    'check_in' => 'required|string',
                    'check_out' => 'required|string',
                ], [
                    'check_in.required' => 'check_in is required when is_all is not true.',
                    'check_out.required' => 'check_out is required when is_all is not true.',
                ]);

                try {
                    $checkIn = Carbon::parse($request->query('check_in'))->startOfDay();
                    $checkOut = Carbon::parse($request->query('check_out'))->endOfDay();
                } catch (Exception $e) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid date format for check_in or check_out.',
                    ], 422);
                }

                if ($checkOut->lt($checkIn)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'check_out cannot be before check_in.',
                    ], 422);
                }
            }

            $query = Venue::query()->with(['amenities', 'media']);
            $checkIn = null;
            $checkOut = null;
            if (! $isAll) {
                $checkIn = Carbon::parse($request->query('check_in'))->startOfDay();
                $checkOut = Carbon::parse($request->query('check_out'))->endOfDay();
            }

            $venues = $query->limit($limit)->get();

            $availableVenueIds = [];
            $blockedDateVenueIds = [];
            $blockedReasonsByVenueId = [];
            $bookedVenueIds = [];
            if (! $isAll && $venues->isNotEmpty()) {
                $venueIds = $venues->pluck('id')->all();
                $availableVenueIds = Venue::whereIn('id', $venueIds)
                    ->availableBetween($checkIn, $checkOut)
                    ->pluck('id')
                    ->all();
                $blockedOverlaps = VenueBlockedDate::query()
                    ->whereIn('venue_id', $venueIds)
                    ->overlappingBookingRange($checkIn, $checkOut)
                    ->get(['venue_id', 'reason']);

                $blockedDateVenueIds = $blockedOverlaps
                    ->pluck('venue_id')
                    ->unique()
                    ->all();

                $blockedReasonsByVenueId = $blockedOverlaps
                    ->groupBy('venue_id')
                    ->map(fn ($group) => $group
                        ->pluck('reason')
                        ->filter(fn ($reason) => filled($reason))
                        ->unique()
                        ->values()
                        ->all()
                    )
                    ->all();

                $bookedVenueIds = Booking::query()
                    ->join('booking_venue', 'bookings.id', '=', 'booking_venue.booking_id')
                    ->whereIn('booking_venue.venue_id', $venueIds)
                    ->where('bookings.booking_status', '!=', Booking::BOOKING_STATUS_CANCELLED)
                    ->where('bookings.check_in', '<', $checkOut)
                    ->where('bookings.check_out', '>', $checkIn)
                    ->distinct()
                    ->pluck('booking_venue.venue_id')
                    ->all();
            }

            $data = VenueResource::collection($venues)->resolve();
            $data = array_map(function ($item) use ($isAll, $availableVenueIds, $blockedDateVenueIds, $blockedReasonsByVenueId, $bookedVenueIds, $venues) {
                $item['available'] = $isAll ? null : in_array($item['id'], $availableVenueIds, true);
                $item['is_block_date'] = $isAll ? null : in_array($item['id'], $blockedDateVenueIds, true);
                $item['unavailability_code'] = null;
                $item['unavailability_title'] = null;
                $item['unavailability_detail'] = null;
                if (! $isAll && $item['available'] === false) {
                    $venue = $venues->firstWhere('id', $item['id']);
                    $u = $this->resolveVenueUnavailability($venue, $blockedReasonsByVenueId, $bookedVenueIds);
                    if ($u !== null) {
                        $item['unavailability_code'] = $u['code'];
                        $item['unavailability_title'] = $u['title'];
                        $item['unavailability_detail'] = $u['detail'];
                    }
                }

                return $item;
            }, $data);

            return response()->json([
                'success' => true,
                'data' => $data,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch venues',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Human-readable reason when a venue is not bookable for the requested range.
     */
    private function resolveVenueUnavailability(?Venue $venue, array $blockedReasonsByVenueId, array $bookedVenueIds): ?array
    {
        if ($venue === null) {
            return null;
        }

        if ($venue->status === Venue::STATUS_MAINTENANCE) {
            return [
                'code' => 'maintenance',
                'title' => 'Under maintenance',
                'detail' => 'This venue is temporarily unavailable for bookings.',
            ];
        }

        if ($venue->status === Venue::STATUS_BOOKED) {
            return [
                'code' => 'booked',
                'title' => 'Not available',
                'detail' => 'This venue is marked as booked and cannot be reserved.',
            ];
        }

        if (array_key_exists($venue->id, $blockedReasonsByVenueId)) {
            $reasonTexts = $blockedReasonsByVenueId[$venue->id] ?? [];

            $detail = count($reasonTexts) > 0
                ? implode(' · ', $reasonTexts)
                : 'One or more days in your range are blocked by staff (e.g. maintenance or private use).';

            return [
                'code' => 'blocked',
                'title' => 'Blocked for your dates',
                'detail' => $detail,
            ];
        }

        if (in_array($venue->id, $bookedVenueIds, true)) {
            return [
                'code' => 'booked',
                'title' => 'Already reserved',
                'detail' => 'Another booking is using this venue for all or part of your selected dates.',
            ];
        }

        return [
            'code' => 'unknown',
            'title' => 'Not available for selected dates',
            'detail' => 'Choose different dates or another venue.',
        ];
    }

    /**
     * Display the specified resource.
     */
    public function show($id): JsonResponse
    {
        try {
            $venue = Venue::with(['amenities', 'media'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => (new VenueResource($venue))->resolve(),
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Venue not found'], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch the venue',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
