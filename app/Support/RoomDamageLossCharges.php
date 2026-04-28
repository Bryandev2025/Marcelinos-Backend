<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

final class RoomDamageLossCharges
{
    const CACHE_KEY = 'room_damage_loss_charges_config';

    /**
     * @return list<array{item: string, charge: string}>
     */
    public static function all(): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            $normalized = self::normalize($cached);
            if ($normalized !== []) {
                return $normalized;
            }
        }

        $envRaw = trim((string) env('ROOM_DAMAGE_LOSS_CHARGES_JSON', ''));
        if ($envRaw !== '') {
            $decoded = json_decode($envRaw, true);
            if (is_array($decoded)) {
                $normalized = self::normalize($decoded);
                if ($normalized !== []) {
                    Cache::forever(self::CACHE_KEY, $normalized);

                    return $normalized;
                }
            }
        }

        $defaults = self::defaultCharges();
        Cache::forever(self::CACHE_KEY, $defaults);

        return $defaults;
    }

    /**
     * @return list<string>
     */
    public static function labels(): array
    {
        return array_values(array_unique(array_map(
            fn (array $row): string => $row['item'],
            self::all(),
        )));
    }

    /**
     * @return list<array{item: string, charge: string}>
     */
    private static function normalize(array $raw): array
    {
        $out = [];

        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }

            $item = trim((string) ($row['item'] ?? $row['label'] ?? $row['name'] ?? ''));
            $charge = trim((string) ($row['charge'] ?? $row['amount'] ?? ''));

            if ($item === '') {
                continue;
            }

            $out[] = [
                'item' => $item,
                'charge' => $charge,
            ];
        }

        return $out;
    }

    /**
     * Matches the current public policy list (frontend modal).
     *
     * @return list<array{item: string, charge: string}>
     */
    private static function defaultCharges(): array
    {
        return [
            ['item' => 'Television', 'charge' => 'Php 25,000.00'],
            ['item' => 'Emergency Lights', 'charge' => 'Php 2,000.00'],
            ['item' => 'Cups and Glass', 'charge' => 'Php 100.00 each'],
            ['item' => 'Lost / Loss of Room Key', 'charge' => 'Php 1,000.00'],
            ['item' => 'Bed Sheet / Blanket / Towel Stain', 'charge' => 'Php 500.00 each'],
            ['item' => 'Slippers', 'charge' => 'Php 100.00 each'],
            ['item' => 'Remote', 'charge' => 'Php 500.00'],
            ['item' => 'Towel', 'charge' => 'Php 500.00 each'],
        ];
    }
}

