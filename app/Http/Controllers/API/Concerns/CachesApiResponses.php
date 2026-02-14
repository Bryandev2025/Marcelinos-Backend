<?php

namespace App\Http\Controllers\API\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * System design: server-side response caching for read-heavy GET endpoints.
 * Use rememberJson() to cache JSON responses and reduce DB load.
 */
trait CachesApiResponses
{
    /** Default TTL in seconds (5 minutes). */
    protected static int $defaultCacheTtl = 300;

    /**
     * Return a JSON response, using cache when a key is provided.
     *
     * @param  string|null  $cacheKey  If null, no caching (e.g. for parameterized availability).
     * @param  int|null  $ttlSeconds  Override default TTL.
     */
    protected function rememberJson(
        ?string $cacheKey,
        callable $callback,
        ?int $ttlSeconds = null
    ): JsonResponse {
        $ttl = $ttlSeconds ?? static::$defaultCacheTtl;

        if ($cacheKey === null || $ttl <= 0) {
            return $callback();
        }

        $cached = Cache::remember($cacheKey, $ttl, function () use ($callback) {
            $response = $callback();
            return [
                'content' => $response->getContent(),
                'status'  => $response->getStatusCode(),
                'headers' => $response->headers->all(),
            ];
        });

        $json = new JsonResponse();
        $json->setContent($cached['content']);
        $json->setStatusCode($cached['status']);
        if (! empty($cached['headers'])) {
            foreach ($cached['headers'] as $key => $values) {
                $json->headers->set($key, is_array($values) ? reset($values) : $values);
            }
        }
        return $json;
    }

    /**
     * Build a cache key for list endpoints with optional query params.
     */
    protected static function listCacheKey(string $resource, array $query = []): ?string
    {
        ksort($query);
        $queryString = http_build_query($query);
        return "api.{$resource}.list." . md5($queryString);
    }
}
