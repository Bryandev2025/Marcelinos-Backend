<?php

namespace App\Support;

final class MaintenancePageVariant
{
    public const DEFAULT = 'resort-hero';

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return [
            'resort-hero',
            'minimal-dawn',
            'midnight-tech',
            'sunset-warm',
            'split-showcase',
            'floating-card',
            'console-status',
            'coastal-breeze',
            'editorial-serif',
            'neon-night',
        ];
    }

    public static function normalize(?string $value): string
    {
        $candidate = is_string($value) ? trim($value) : '';

        return in_array($candidate, self::keys(), true) ? $candidate : self::DEFAULT;
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'resort-hero' => 'Resort hero — banner photo & glass card',
            'minimal-dawn' => 'Minimal dawn — light, airy, centered',
            'midnight-tech' => 'Midnight tech — dark grid & cyan accents',
            'sunset-warm' => 'Sunset warm — full-screen warm gradient',
            'split-showcase' => 'Split showcase — image left, copy right',
            'floating-card' => 'Floating card — blurred backdrop & elevated panel',
            'console-status' => 'Console status — monospace system-style',
            'coastal-breeze' => 'Coastal breeze — teal gradient & soft shapes',
            'editorial-serif' => 'Editorial — magazine headline & minimal chrome',
            'neon-night' => 'Neon night — dark with violet & magenta glow',
        ];
    }
}
