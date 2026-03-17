<?php

namespace App\Filament\Resources\Rooms\Pages;

use App\Filament\Resources\Rooms\RoomResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRoom extends EditRecord
{
    protected static string $resource = RoomResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['amenities'] = $this->record->amenities->pluck('id')->all();
        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
