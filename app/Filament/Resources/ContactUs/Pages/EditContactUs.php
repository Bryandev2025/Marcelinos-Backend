<?php

namespace App\Filament\Resources\ContactUs\Pages;

use App\Filament\Resources\ContactUs\ContactUsResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;

class EditContactUs extends EditRecord
{
    protected static string $resource = ContactUsResource::class;

    public function form(Schema $schema): Schema
    {
        return ContactUsResource::form($schema);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
