<?php

namespace App\Filament\Resources\Rooms\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Schemas\Schema;

class RoomForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required(),
                TextInput::make('capacity')->required()->numeric(),
                Select::make('type')
                    ->options(['standard'=>'Standard','family'=>'Family','deluxe'=>'Deluxe'])
                    ->required(),
                TextInput::make('price')->required()->numeric()->prefix('₱'),
                Select::make('status')
                    ->options(['available'=>'Available','occupied'=>'Occupied','cleaning'=>'Cleaning','maintenance'=>'Maintenance'])
                    ->default('available')
                    ->required(),
                SpatieMediaLibraryFileUpload::make('featured_image')
                    ->collection('featured')
                    ->label('Featured Image')
                    ->image()
                    ->required(),
                SpatieMediaLibraryFileUpload::make('gallery_images')
                    ->collection('gallery')
                    ->multiple()
                    ->label('Gallery Images')
                    ->image(),
            ]);
    }
}
