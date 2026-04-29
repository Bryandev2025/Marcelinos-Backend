<?php

namespace App\Filament\Resources\DamageProperties\Pages;

use App\Filament\Resources\DamageProperties\DamagePropertyResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDamageProperty extends ViewRecord
{
    protected static string $resource = DamagePropertyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}

