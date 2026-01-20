<?php

namespace App\Http\Controllers\API;

use App\Models\Room;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class RoomController extends Controller
{
    public function index()
    {
        try {
            $rooms = Room::latest()->get();

            return response()->json([
                'success' => true,
                'data' => $rooms
            ], 200);

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
