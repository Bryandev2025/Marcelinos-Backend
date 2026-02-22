<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Concerns\CachesApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Resources\API\VenueResource;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Exception;

class VenueController extends Controller
{
    use CachesApiResponses;

    /**
     * List venues.
     * Same availability contract as RoomController: when check_in/check_out are provided,
     * All venues are returned but add availability status (available or not available).
     * - is_all=true: return all venues (e.g. homepage).
     * - Otherwise: require check_in & check_out; return venues with availability status based on the date range.
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

                $cacheKey = static::listCacheKey('venues', [
                    'check_in'  => $checkIn->toDateString(),
                    'check_out' => $checkOut->toDateString(),
                ]);
                $ttl = 120;
            } else {
                $cacheKey = 'api.venues.list.all';
                $ttl = 300;
            }

            return $this->rememberJson($cacheKey, function () use ($request) {
                $isAll = filter_var($request->query('is_all', false), FILTER_VALIDATE_BOOLEAN);

                $query = Venue::query()->with(['amenities', 'media']);

                $checkIn = null;
                $checkOut = null;
                if (!$isAll) {
                    $checkIn  = Carbon::parse($request->query('check_in'))->startOfDay();
                    $checkOut = Carbon::parse($request->query('check_out'))->endOfDay();
                    // Return all venues; availability status is added per venue below
                }

                $venues = $query->get();

                $availableVenueIds = [];
                if (!$isAll && $venues->isNotEmpty()) {
                    $availableVenueIds = Venue::whereIn('id', $venues->pluck('id'))
                        ->availableBetween($checkIn, $checkOut)
                        ->pluck('id')
                        ->all();
                }

                $data = VenueResource::collection($venues)->resolve();
                $data = array_map(function ($item) use ($isAll, $availableVenueIds) {
                    $item['available'] = $isAll ? null : in_array($item['id'], $availableVenueIds);
                    return $item;
                }, $data);

                $payload = [
                    'success' => true,
                    'data' => $data,
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
                'message' => 'Failed to fetch venues',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource (cached for performance).
     */
    public function show($id): JsonResponse
    {
        $cacheKey = "api.venues.show.{$id}";
        return $this->rememberJson($cacheKey, function () use ($id) {
            try {
                $venue = Venue::with(['amenities', 'media'])->findOrFail($id);
                $json = response()->json([
                    'success' => true,
                    'data' => (new VenueResource($venue))->resolve(),
                ], 200);
                $json->header('Cache-Control', 'public, max-age=300');
                return $json;
            } catch (ModelNotFoundException $e) {
                return response()->json(['success' => false, 'message' => 'Venue not found'], 404);
            } catch (Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch the venue',
                    'error' => $e->getMessage(),
                ], 500);
            }
        });
    }
}
