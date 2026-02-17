<?php

namespace App\Http\Controllers\API;

use App\Models\Room;
use App\Http\Controllers\Controller;
use App\Http\Controllers\API\Concerns\CachesApiResponses;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Exception;

class RoomController extends Controller
{
    use CachesApiResponses;
    /**
     * List rooms.
     * Same availability contract as VenueController: when check_in/check_out are provided,
     * only rooms available in that range are returned (no overlapping non-cancelled bookings, not in maintenance).
     * - is_all=true: return all rooms (e.g. homepage).
     * - Otherwise: require check_in & check_out; return only rooms available in that date range.
     */
    public function index(Request $request)
    {
        try {
            $isAll = filter_var($request->query('is_all', false), FILTER_VALIDATE_BOOLEAN);

            $query = Room::with(['amenities', 'media'])
                ->orderByRaw("FIELD(type, 'standard', 'family', 'deluxe')");

            if (!$isAll) {
                $request->validate([
                    'check_in'  => 'required|string',
                    'check_out' => 'required|string',
                ], [
                    'check_in.required'  => 'check_in is required when is_all is not true.',
                    'check_out.required' => 'check_out is required when is_all is not true.',
                ]);

                // Use same range as BookingController (startOfDay + endOfDay) so list matches conflict logic
                try {
                    $checkIn  = Carbon::parse($request->query('check_in'))->startOfDay();
                    $checkOut = Carbon::parse($request->query('check_out'))->endOfDay();
                } catch (\Exception $e) {
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

                $query->availableBetween($checkIn, $checkOut);
            }

            $rooms = $query->get();

            $formattedRooms = $rooms->map(function ($room) {
                return [
                    'id' => $room->id,
                    'name' => $room->name,
                    'description' => $room->description,
                    'capacity' => $room->capacity,
                    'type' => $room->type,
                    'price' => $room->price,
                    'status' => $room->status,
                    'amenities' => $room->amenities,
                    'featured_image' => $room->featured_image_url,
                    'gallery' => $room->gallery_urls,
                ];
            });

            $payload = ['success' => true, 'data' => $formattedRooms];
            $isAll = filter_var($request->query('is_all', false), FILTER_VALIDATE_BOOLEAN);
            $cacheKey = $isAll ? 'api.rooms.list.all' : null;
            $ttl = $isAll ? 300 : 0;

            return $this->rememberJson($cacheKey, fn () => response()->json($payload, 200), $ttl);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch rooms',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id): JsonResponse
    {
        $cacheKey = "api.rooms.show.{$id}";
        return $this->rememberJson($cacheKey, function () use ($id) {
            try {
                $room = Room::with(['amenities', 'media'])->findOrFail($id);
                return response()->json([
                    'success' => true,
                    'data' => [
                        'id' => $room->id,
                        'name' => $room->name,
                        'description' => $room->description,
                        'capacity' => $room->capacity,
                        'type' => $room->type,
                        'price' => $room->price,
                        'status' => $room->status,
                        'amenities' => $room->amenities,
                        'featured_image' => $room->featured_image_url,
                        'gallery' => $room->gallery_urls,
                    ],
                ], 200);
            } catch (ModelNotFoundException $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Room not found',
                ], 404);
            } catch (Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch the room',
                    'error' => $e->getMessage(),
                ], 500);
            }
        });
    }
}