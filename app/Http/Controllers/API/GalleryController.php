<?php

namespace App\Http\Controllers\API;

use App\Models\Gallery;
use App\Http\Controllers\Controller;
use App\Http\Controllers\API\Concerns\CachesApiResponses;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class GalleryController extends Controller
{
    use CachesApiResponses;

    /**
     * List gallery images.
     */
    public function index(Request $request): JsonResponse
    {
        $cacheKey = 'api.galleries.list';
        return $this->rememberJson($cacheKey, function () {
            try {
                $galleries = Gallery::with('media')->get();

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
            } catch (Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch gallery images',
                    'error' => $e->getMessage(),
                ], 500);
            }
        }, 300); // Cache for 5 minutes
    }

    /**
     * Display the specified gallery image.
     */
    public function show($id): JsonResponse
    {
        $cacheKey = "api.galleries.show.{$id}";
        return $this->rememberJson($cacheKey, function () use ($id) {
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
        });
    }
}
