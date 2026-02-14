<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Controllers\API\Concerns\CachesApiResponses;
use App\Models\BlockedDate;
use Illuminate\Http\JsonResponse;

class BlockedDateController extends Controller
{
    use CachesApiResponses;

    /**
     * Return all blocked dates as JSON (cached for performance).
     */
    public function index(): JsonResponse
    {
        $blockedDates = BlockedDate::select('date', 'reason')->get(); 

        return response()->json([
            'blocked_dates' => $blockedDates
        ]);
    }
}
