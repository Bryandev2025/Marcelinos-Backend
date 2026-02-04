<?php

namespace App\Http\Controllers\API;

use App\Models\Room;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Exception;

class RoomController extends Controller
{
    /**
     * List rooms.
     * - is_all=true: return all rooms.
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

                try {
                    $checkIn  = Carbon::parse($request->query('check_in'))->startOfDay();
                    $checkOut = Carbon::parse($request->query('check_out'))->startOfDay();
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
                    'capacity' => $room->capacity,
                    'type' => $room->type,
                    'price' => $room->price,
                    'status' => $room->status,
                    'amenities' => $room->amenities,
                    'featured_image' => $room->featured_image_url,
                    'gallery' => $room->gallery_urls,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedRooms,
            ], 200);
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

    public function show($id)
    {
        try {
            $room = Room::with(['amenities', 'media'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $room->id,
                    'name' => $room->name,
                    'capacity' => $room->capacity,
                    'type' => $room->type,
                    'price' => $room->price,
                    'status' => $room->status,
                    'amenities' => $room->amenities,
                    'featured_image' => $room->featured_image_url,
                    'gallery' => $room->gallery_urls,
                ]
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Room not found'
            ], 404);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch the room',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}