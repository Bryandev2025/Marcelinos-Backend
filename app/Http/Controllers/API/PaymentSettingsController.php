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
                    'partial_payment_options' => $this->normalizePartialPaymentOptions($cached['partial_payment_options'] ?? null),
                    'allow_custom_partial_payment' => (bool) ($cached['allow_custom_partial_payment'] ?? false),
                ],
            ]);
        }

        $data = [
            'online_payment_enabled' => filter_var(env('PAYMENT_ONLINE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
            'partial_payment_options' => $this->normalizePartialPaymentOptions((string) env('PAYMENT_PARTIAL_OPTIONS', '10,20,30')),
            'allow_custom_partial_payment' => filter_var(env('PAYMENT_PARTIAL_ALLOW_CUSTOM', false), FILTER_VALIDATE_BOOLEAN),
        ];

        Cache::forever('payment_settings_config', $data);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * @return array<int>
     */
    private function normalizePartialPaymentOptions(mixed $raw): array
    {
        $parts = is_array($raw) ? $raw : explode(',', (string) $raw);

        $values = collect($parts)
            ->map(fn ($v): int => (int) trim((string) $v))
            ->filter(fn (int $v): bool => $v > 0 && $v < 100)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $values !== [] ? $values : [30];
    }
}
