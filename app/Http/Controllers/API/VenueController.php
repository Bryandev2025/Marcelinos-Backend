<?php

namespace App\Http\Controllers\API;

use App\Models\Venue;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Exception;

class VenueController extends Controller
{
    /**
     * List venues.
     * - is_all=true: return all venues.
     * - Otherwise: require check_in & check_out; return only venues available in that date range.
     */
    public function index(Request $request)
    {
        try {
            $isAll = filter_var($request->query('is_all', false), FILTER_VALIDATE_BOOLEAN);

            $query = Venue::query()->with(['amenities', 'media']);

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

            $venues = $query->get();

            $formattedVenues = $venues->map(function ($venue) {
                return [
                    'id' => $venue->id,
                    'name' => $venue->name,
                    'description' => $venue->description,
                    'capacity' => $venue->capacity,
                    'price' => $venue->price,
                    'amenities' => $venue->amenities,
                    'featured_image' => $venue->featured_image_url,
                    'gallery' => $venue->gallery_urls,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedVenues,
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
                'message' => 'Failed to fetch venues',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
    */
    public function show($id)
    {
        try {
            $venue = Venue::with(['amenities', 'media'])->findOrFail($id);

            $data = [
                'id' => $venue->id,
                'name' => $venue->name,
                'description' => $venue->description,
                'capacity' => $venue->capacity,
                'price' => $venue->price,
                'amenities' => $venue->amenities,
                'featured_image' => $venue->featured_image_url,
                'gallery' => $venue->gallery_urls,
            ];

            return response()->json([
                'success' => true,
                'data' => $data
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
