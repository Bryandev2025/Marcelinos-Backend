<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class PaymentSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        $cached = Cache::get('payment_settings_config');

        if (is_array($cached)) {
            return response()->json([
                'success' => true,
                'data' => [
                    'online_payment_enabled' => (bool) ($cached['online_payment_enabled'] ?? false),
                ],
            ]);
        }

        $data = [
            'online_payment_enabled' => filter_var(env('PAYMENT_ONLINE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        ];

        Cache::forever('payment_settings_config', $data);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
