<?php

namespace App\Filament\Resources\BedSpecifications\Pages;

use App\Filament\Resources\BedSpecifications\BedSpecificationResource;
use App\Models\BedSpecification;
use App\Support\ActivityLogger;
use Filament\Resources\Pages\CreateRecord;

class CreateBedSpecification extends CreateRecord
{
    protected static string $resource = BedSpecificationResource::class;

    protected function afterCreate(): void
    {
        $user = auth()->user();
        $role = strtolower(trim((string) ($user?->role ?? '')));

        if (! $user || ! in_array($role, ['admin', 'staff'], true)) {
            return;
        }

        $parts = [];
        $featuredMedia = $this->record->getFirstMedia('featured');
        $galleryCount = $this->record->getMedia('gallery')->count();

        if ($featuredMedia !== null) {
            $parts[] = 'featured image uploaded';
        }

        if ($galleryCount > 0) {
            $parts[] = sprintf('gallery image%s uploaded (%d)', $galleryCount > 1 ? 's' : '', $galleryCount);
        }

        if ($parts === []) {
            return;
        }

        ActivityLogger::log(
            category: 'resource',
            event: 'resource.created',
            description: sprintf('Bed specification images uploaded: %s (%s).', $this->record->specification, implode('; ', $parts)),
            subject: $this->record,
            meta: [
                'model' => BedSpecification::class,
                'id' => $this->record->getKey(),
                'featured_uploaded' => $featuredMedia !== null,
                'gallery_uploaded_count' => $galleryCount,
            ],
            userId: (int) $user->id,
        );
    }
}
