<?php

namespace App\Filament\Resources\BedSpecifications\Pages;

use App\Filament\Actions\TypedDeleteAction;
use App\Filament\Actions\TypedForceDeleteAction;
use App\Filament\Resources\BedSpecifications\BedSpecificationResource;
use App\Models\BedSpecification;
use App\Support\ActivityLogger;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditBedSpecification extends EditRecord
{
    protected static string $resource = BedSpecificationResource::class;
    protected ?int $originalFeaturedMediaId = null;
    protected array $originalGalleryMediaIds = [];

    protected function getHeaderActions(): array
    {
        if ($this->record->trashed()) {
            return [
                RestoreAction::make(),
                TypedForceDeleteAction::make(fn (BedSpecification $record): string => $record->specification),
            ];
        }

        return [
            TypedDeleteAction::make(fn (BedSpecification $record): string => $record->specification),
        ];
    }

    protected function beforeSave(): void
    {
        $this->originalFeaturedMediaId = $this->record->getFirstMedia('featured')?->id;
        $this->originalGalleryMediaIds = $this->record->getMedia('gallery')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    protected function afterSave(): void
    {
        $user = auth()->user();
        $role = strtolower(trim((string) ($user?->role ?? '')));

        if (! $user || ! in_array($role, ['admin', 'staff'], true)) {
            return;
        }

        $currentFeaturedMediaId = $this->record->getFirstMedia('featured')?->id;
        $currentGalleryMediaIds = $this->record->getMedia('gallery')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $parts = [];

        if ($currentFeaturedMediaId !== null && $currentFeaturedMediaId !== $this->originalFeaturedMediaId) {
            $parts[] = 'featured image uploaded';
        }

        $addedGalleryCount = count(array_diff($currentGalleryMediaIds, $this->originalGalleryMediaIds));
        if ($addedGalleryCount > 0) {
            $parts[] = sprintf(
                'gallery image%s uploaded (%d)',
                $addedGalleryCount > 1 ? 's' : '',
                $addedGalleryCount
            );
        }

        if ($parts === []) {
            return;
        }

        ActivityLogger::log(
            category: 'resource',
            event: 'resource.updated',
            description: sprintf('Bed specification images updated: %s (%s).', $this->record->specification, implode('; ', $parts)),
            subject: $this->record,
            meta: [
                'model' => BedSpecification::class,
                'id' => $this->record->getKey(),
                'featured_uploaded' => in_array('featured image uploaded', $parts, true),
                'gallery_uploaded_count' => $addedGalleryCount,
            ],
            userId: (int) $user->id,
        );
    }
}
