<?php

namespace App\Filament\Resources\DamageProperties\Pages;

use App\Filament\Resources\DamageProperties\DamagePropertyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDamageProperty extends EditRecord
{
    protected static string $resource = DamagePropertyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

