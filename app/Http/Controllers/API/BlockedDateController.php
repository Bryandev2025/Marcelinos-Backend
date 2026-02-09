<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Controllers\API\Concerns\CachesApiResponses;
use App\Models\BlockedDate;
use Illuminate\Http\JsonResponse;

class BlockedDateController extends Controller
{
    use CachesApiResponses;

    /** Cache TTL for blocked dates list (10 min). */
    protected static int $defaultCacheTtl = 600;

    /**
     * Return all blocked dates as JSON (cached for performance).
     */
    public function index(): JsonResponse
    {
        return $this->rememberJson('api.blocked-dates', function () {
            $blockedDates = BlockedDate::pluck('date');
            return response()->json(['blocked_dates' => $blockedDates]);
        });
    }
}
