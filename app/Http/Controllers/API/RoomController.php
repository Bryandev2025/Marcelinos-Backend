<?php

namespace App\Http\Controllers\API;

use App\Models\Room;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function index()
    {
         $rooms = Room::latest()->get();

        return response()->json([
            'success' => true,
            'data' => $rooms
        ], 200);
    }

    public function show($id)
{
    $room = Room::find($id);

    if (!$room) {
        return response()->json([
            'success' => false,
            'message' => 'Room not found'
        ], 404);
    }

    return response()->json([
        'success' => true,
        'data' => $room
    ], 200);
}
}
