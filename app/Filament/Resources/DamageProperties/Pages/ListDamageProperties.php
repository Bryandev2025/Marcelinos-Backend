<?php

namespace App\Filament\Resources\DamageProperties\Pages;

use App\Filament\Resources\DamageProperties\DamagePropertyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDamageProperties extends ListRecords
{
    protected static string $resource = DamagePropertyResource::class;

    protected static ?string $title = 'Damage Properties';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New property'),
        ];
    }
}

