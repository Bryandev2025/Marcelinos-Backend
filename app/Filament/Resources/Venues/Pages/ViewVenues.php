<?php

namespace App\Filament\Resources\Venues\Pages;

use App\Filament\Resources\Venues\VenuesResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVenues extends ViewRecord
{
    protected static string $resource = VenuesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
