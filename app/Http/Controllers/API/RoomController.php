<?php

namespace App\Http\Controllers\API;

use App\Models\Room;
use App\Http\Controllers\Controller;
<<<<<<< HEAD
=======
use App\Models\Booking;
use Illuminate\Auth\CreatesUserProviders;
use Illuminate\Http\Request;
>>>>>>> 7aba369 (1)
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class RoomController extends Controller
{
    public function index()
    {
        try {
            /**
             * Fetch all rooms and it's related image on Image model
             * with('mainImage','gallery','amenities') to eager load relationships
             * fetch by order [standard, family, deluxe]
             * fetch only rooms with status 'available'
            */
            $rooms = Room::with('mainImage', 'gallery', 'amenities')
                ->where('status', 'available')
                ->orderByRaw("FIELD(type, 'standard', 'family', 'deluxe')")
                ->get();
            return response()->json($rooms, 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch rooms',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $room = Room::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $room
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