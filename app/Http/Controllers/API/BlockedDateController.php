<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\BlockedDate;
use Illuminate\Http\Request;

class BlockedDateController extends Controller
{
    /**
     * Return all blocked dates as JSON
     */
    public function index()
    {
        $blockedDates = BlockedDate::select('date', 'reason')->get(); 

        return response()->json([
            'blocked_dates' => $blockedDates
        ]);
    }
}
