<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class Venue extends Controller
{
    // DISPLAY A LIST OF VENUES
    public function index()
    {
        $venues = Venue::all();

        return response()->json([
            'success' => true,
            'data' => $venues,
        ]);
    }
    
    // STORE A NEW VENUE
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'capacity' => 'required|integer|min:0',
            'price' => 'required|numeric|min:0',
        ]);

        $venue = Venue::create($validated);

        return response()->json([
            'success' => true,
            'data' => $venue,
        ], Response::HTTP_CREATED);
    }
    
    // SHOW A SPECIFIC VENUE
    public function show($id)
    {
        $venue = Venue::find($id);

        if (!$venue) {
            return response()->json([
                'success' => false,
                'message' => 'Venue not found',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'data' => $venue,
        ]);
    }

    // UPDATE A SPECIFIC VENUE
    public function update(Request $request, $id)
    {
        $venue = Venue::find($id);

        if (!$venue) {
            return response()->json([
                'success' => false,
                'message' => 'Venue not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'capacity' => 'sometimes|required|integer|min:0',
            'price' => 'sometimes|required|numeric|min:0',
        ]);

        $venue->update($validated);

        return response()->json([
            'success' => true,
            'data' => $venue,
        ]);
    }

    // DELETE A SPECIFIC VENUE
    public function destroy($id)
    {
        $venue = Venue::find($id);

        if (!$venue) {
            return response()->json([
                'success' => false,
                'message' => 'Venue not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $venue->delete();

        return response()->json([
            'success' => true,
            'message' => 'Venue deleted successfully',
        ]);
    }

}
