<?php

namespace App\Filament\Resources\Venues\Pages;

use App\Filament\Actions\TypedDeleteAction;
use App\Filament\Actions\TypedForceDeleteAction;
use App\Filament\Resources\Venues\VenuesResource;
use App\Support\ActivityLogger;
use App\Models\Venue;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditVenues extends EditRecord
{
    protected static string $resource = VenuesResource::class;
    protected array $originalAmenityIds = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['amenities'] = $this->record->amenities->pluck('id')->all();

        return $data;
    }

    protected function getHeaderActions(): array
    {
        if ($this->record->trashed()) {
            return [
                RestoreAction::make(),
                TypedForceDeleteAction::make(fn (Venue $record): string => $record->name),
            ];
        }

        return [
            TypedDeleteAction::make(fn (Venue $record): string => $record->name),
        ];
    }

    protected function beforeSave(): void
    {
        $this->originalAmenityIds = $this->record
            ->amenities()
            ->pluck('amenities.id')
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

        $currentAmenityIds = $this->record
            ->amenities()
            ->pluck('amenities.id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $addedIds = array_values(array_diff($currentAmenityIds, $this->originalAmenityIds));
        $removedIds = array_values(array_diff($this->originalAmenityIds, $currentAmenityIds));

        if ($addedIds === [] && $removedIds === []) {
            return;
        }

        $addedNames = $this->record->amenities()
            ->whereIn('amenities.id', $addedIds)
            ->orderBy('name')
            ->pluck('name')
            ->all();

        $removedNames = \App\Models\Amenity::query()
            ->whereIn('id', $removedIds)
            ->orderBy('name')
            ->pluck('name')
            ->all();

        $parts = [];

        if ($addedNames !== []) {
            $parts[] = 'added '.implode(', ', $addedNames);
        }

        if ($removedNames !== []) {
            $parts[] = 'removed '.implode(', ', $removedNames);
        }

        ActivityLogger::log(
            category: 'resource',
            event: 'resource.updated',
            description: sprintf('Venue updated: %s (amenities: %s).', $this->record->name, implode('; ', $parts)),
            subject: $this->record,
            meta: [
                'model' => Venue::class,
                'id' => $this->record->getKey(),
                'amenities_added' => $addedNames,
                'amenities_removed' => $removedNames,
            ],
            userId: (int) $user->id,
        );
    }
}
