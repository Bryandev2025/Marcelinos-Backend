<?php

namespace App\Http\Controllers\API;

use App\Models\Room;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Auth\CreatesUserProviders;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class RoomController extends Controller
{
    public function index()
{
    try {
        /**
         * Fetch available rooms with amenities
         * Sorted by type (Standard, Family, Deluxe)
         */
        $rooms = Room::with(['amenities', 'media']) // Eager load Spatie media to avoid N+1 issues
            ->where('status', 'available')
            ->orderByRaw("FIELD(type, 'standard', 'family', 'deluxe')")
            ->get();

        // Transform the data to include media URLs for React
        $formattedRooms = $rooms->map(function ($room) {
            return [
                'id' => $room->id,
                'name' => $room->name,
                'capacity' => $room->capacity,
                'type' => $room->type,
                'price' => $room->price,
                'status' => $room->status,
                'amenities' => $room->amenities,
                // Get the URL for the 'featured' collection
                'featured_image' => $room->getFirstMediaUrl('featured'),
                // Get all URLs for the 'gallery' collection
                'gallery' => $room->getMedia('gallery')->map(fn($media) => $media->getUrl()),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedRooms
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