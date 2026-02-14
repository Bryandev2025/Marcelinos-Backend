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
        try {
            $isAll = filter_var($request->query('is_all', false), FILTER_VALIDATE_BOOLEAN);

            $query = Gallery::with('media');

            // Optionally, add more filters here if needed

            $galleries = $query->get();

            $formattedGalleries = $galleries->map(function ($gallery) {
                return [
                    'id' => $gallery->id,
                    'image' => $gallery->image_url,
                ];
            });

            $payload = [
                'success' => true,
                'data' => $formattedGalleries,
            ];

            $cacheKey = $isAll ? 'api.galleries.list.all' : 'api.galleries.list';
            $ttl = $isAll ? 300 : 0;

            return $this->rememberJson($cacheKey, fn () => response()->json($payload, 200), $ttl);
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
