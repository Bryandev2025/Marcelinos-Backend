<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function index()
    {
        $rooms = Room::all();
        return response()->json($rooms);
    }

    public function show($id)
    {
        $room = Room::find($id);
    
        if (!$room) {
            return response()->json(['message' => 'Room not found.'], 404);
        }

        return response()->json($room);
    }
}
