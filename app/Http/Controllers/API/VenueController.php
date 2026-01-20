<?php

namespace App\Http\Controllers\API;

use App\Models\Venue;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class VenueController extends Controller
{
    public function index()
    {
        try {
            $venues = Venue::latest()->get();

            return response()->json([
                'success' => true,
                'data' => $venues
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch venues',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $venue = Venue::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $venue
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Venue not found'
            ], 404);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch the venue',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
