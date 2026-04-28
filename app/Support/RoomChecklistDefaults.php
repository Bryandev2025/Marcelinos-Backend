<?php

namespace App\Support;

final class RoomChecklistDefaults
{
    /**
     * @return list<string>
     */
    public static function labels(): array
    {
        return [
            'Door lock / key',
            'Windows / curtains',
            'Lights / switches',
            'Aircon / remote',
            'TV / remote',
            'Wi‑Fi info card (if applicable)',
            'Bed / mattress condition',
            'Bedsheets / pillowcases',
            'Pillows',
            'Blanket / comforter',
            'Towels (bath / hand)',
            'Toiletries (soap, shampoo, tissue)',
            'Bathroom cleanliness',
            'Shower / faucet working',
            'Toilet flush working',
            'Mirror',
            'Trash bin',
            'Table / chairs',
            'Cabinet / hangers',
            'Overall room cleanliness',
        ];
    }
}

