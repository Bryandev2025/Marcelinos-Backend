<?php

namespace App\Http\Controllers\API;

use App\Models\Gallery;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class GalleryController extends Controller
{
    /**
     * List gallery images.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Gallery::with('media');
            $galleries = $query->get();

            $formattedGalleries = $galleries->map(function ($gallery) {
                return [
                    'id' => $gallery->id,
                    'image' => $gallery->image_url,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedGalleries,
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
                'message' => 'Failed to fetch gallery images',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified gallery image.
     */
    public function show($id): JsonResponse
    {
        try {
            $gallery = Gallery::with('media')->findOrFail($id);
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $gallery->id,
                    'image' => $gallery->image_url,
                ],
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gallery image not found',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch the gallery image',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
