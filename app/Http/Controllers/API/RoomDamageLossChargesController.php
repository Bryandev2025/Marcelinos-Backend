<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Support\RoomDamageLossCharges;
use Illuminate\Http\JsonResponse;

class RoomDamageLossChargesController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'charges' => RoomDamageLossCharges::all(),
            ],
        ]);
    }
}

