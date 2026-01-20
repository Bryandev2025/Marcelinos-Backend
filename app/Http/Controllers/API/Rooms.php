<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Room;

class Rooms extends Controller
{
    // 📋 Get all rooms
    public function index()
    {
        $rooms = Room::all();
        return response()->json($rooms);
    }

    // 🔍 Get single room
    public function show($id)
    {
        $room = Room::find($id);

        if (!$room) {
            return response()->json(['message' => 'Room not found.'], 404);
        }

        return response()->json($room);
    }

    // 🏠 Create a new room
    public function store(Request $request)
{
    $validated = $request->validate([
        'room_number' => 'required|string|unique:rooms,room_number',
        'type'        => 'required|string',
        'capacity'    => 'required|integer|min:1',
        'price'       => 'required|numeric|min:0',
        'description' => 'nullable|string',
    ]);

    // Set default status
    $validated['status'] = 'Available';

    $room = Room::create($validated);

    return response()->json([
        'message' => 'Room created successfully.',
        'room'    => $room,
    ], 201);
}


    // ✏️ Update existing room
    public function update(Request $request, $id)
    {
        $room = Room::find($id);

        if (!$room) {
            return response()->json(['message' => 'Room not found.'], 404);
        }

        $validated = $request->validate([
            'room_number' => 'sometimes|string|unique:rooms,room_number,' . $room->id,
            'type' => 'sometimes|string',
            'capacity' => 'sometimes|integer|min:1',
            'price' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|string|in:Available,Occupied,Maintenance',
            'description' => 'nullable|string',
        ]);

        $room->update($validated);

        return response()->json([
            'message' => 'Room updated successfully.',
            'room' => $room
        ]);
    }

    // 🗑 Delete room
    public function destroy($id)
    {
        $room = Room::find($id);

        if (!$room) {
            return response()->json(['message' => 'Room not found.'], 404);
        }

        $room->delete();

        return response()->json(['message' => 'Room deleted successfully.']);
    }
}
