<?php

namespace App\Filament\Resources\Reviews\Pages;

use App\Filament\Resources\Reviews\ReviewResource;
use Filament\Resources\Pages\CreateRecord;

class CreateReview extends CreateRecord
{
    protected static string $resource = ReviewResource::class;
        /**
     * Modify the form data before creating the review.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Automatically set reviewed_at timestamp
        $data['reviewed_at'] = now();

        return $data;
    }
}
