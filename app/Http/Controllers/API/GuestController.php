<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Guest;
// use Carbon\Carbon;

class GuestController extends Controller
{
    public function index()
    {
        $guests = Guest::all();
        return response()->json($guests);
    }

        public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name'       => 'required|string|max:100',
            'middle_name'      => 'nullable|string|max:100',
            'last_name'        => 'required|string|max:100',
            'email'            => 'required|email|unique:guests,email',
            'contact_num'      => 'required|string|max:20',

            'gender'           => 'nullable|in:Male,Female,Other',

            'id_type'          => 'required|string|max:50', // Passport, PhilID
            'id_number'        => 'required|string|max:100',

            'is_international' => 'required|boolean',
            'country'          => 'nullable|string|max:100',

            // PH Address
            'province'         => 'nullable|string|max:100',
            'municipality'     => 'nullable|string|max:100',
            'barangay'         => 'nullable|string|max:100',

            // International Address
            'city'             => 'nullable|string|max:100',
            'state_region'     => 'nullable|string|max:100',
        ]);

        // Default country logic
        if (!$validated['is_international']) {
            $validated['country'] = 'Philippines';
            $validated['city'] = null;
            $validated['state_region'] = null;
        } else {
            $validated['province'] = null;
            $validated['municipality'] = null;
            $validated['barangay'] = null;
        }

        $guest = Guest::create($validated);

        return response()->json([
            'message' => 'Guest successfully created!',
            'data' => $guest,
            'guest_id' => $guest->id
        ], 201);
    }
    public function destroy($id)
    {
        $guest = Guest::find($id);

        if (!$guest) {
            return response()->json(['message' => 'Guest Record not found.'], 404);
        }

        $guest->delete();

        return response()->json(['message' => 'Guest record deleted successfully.']);
    }
}
