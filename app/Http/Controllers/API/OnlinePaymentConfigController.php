<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class OnlinePaymentConfigController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'enabled' => filter_var(env('ONLINE_PAYMENT_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
                'provider' => 'xendit',
                'public_key' => (string) env('XENDIT_PUBLIC_KEY', ''),
            ],
        ]);
    }
}
