<?php

namespace App\Filament\Resources\Rooms\Schemas;

use App\Models\Room;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Schemas\Schema;

use Filament\Forms\Components\Textarea;

class RoomForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required(),
                Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull(),
                TextInput::make('capacity')->required()->numeric(),
                Select::make('type')
                    ->options(Room::typeOptions())
                    ->required(),
                TextInput::make('price')->required()->numeric()->prefix('₱'),
                Select::make('status')
                    ->options(Room::statusOptions())
                    ->default(Room::STATUS_AVAILABLE)
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
                Select::make('amenities')
                    ->relationship('amenities', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable(),
            ]);
    }
}
