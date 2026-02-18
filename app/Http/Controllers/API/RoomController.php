<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Concerns\CachesApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Resources\API\RoomResource;
use App\Models\Room;
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

            if (!$isAll) {
                $request->validate([
                    'check_in'  => 'required|string',
                    'check_out' => 'required|string',
                ], [
                    'check_in.required'  => 'check_in is required when is_all is not true.',
                    'check_out.required' => 'check_out is required when is_all is not true.',
                ]);

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

                $cacheKey = static::listCacheKey('rooms', [
                    'check_in'  => $checkIn->toDateString(),
                    'check_out' => $checkOut->toDateString(),
                ]);
                $ttl = 120;
            } else {
                $cacheKey = 'api.rooms.list.all';
                $ttl = 300;
            }

            return $this->rememberJson($cacheKey, function () use ($request) {
                $isAll = filter_var($request->query('is_all', false), FILTER_VALIDATE_BOOLEAN);

                $query = Room::with(['amenities', 'media'])
                    ->orderByRaw("FIELD(type, 'standard', 'family', 'deluxe')");

                if (!$isAll) {
                    $checkIn  = Carbon::parse($request->query('check_in'))->startOfDay();
                    $checkOut = Carbon::parse($request->query('check_out'))->endOfDay();
                    $query->availableBetween($checkIn, $checkOut);
                }

                $rooms = $query->get();
                $payload = [
                    'success' => true,
                    'data' => RoomResource::collection($rooms)->resolve(),
                ];
                $response = response()->json($payload, 200);
                $response->header('Cache-Control', $isAll ? 'public, max-age=300' : 'public, max-age=120');
                return $response;
            }, $ttl);
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
                $json = response()->json([
                    'success' => true,
                    'data' => (new RoomResource($room))->resolve(),
                ], 200);
                $json->header('Cache-Control', 'public, max-age=300');
                return $json;
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
