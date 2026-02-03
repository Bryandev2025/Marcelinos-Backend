<?php

namespace App\Filament\Resources\Venues\Schemas;

use App\Models\Venue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Schemas\Schema;

class VenuesForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Venue Name')
                    ->required(),

                TextInput::make('capacity')
                    ->required()
                    ->numeric(),

                // Kept the price prefix to match your local currency style
                TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->prefix('₱'),

                Select::make('status')
                    ->options(Venue::statusOptions())
                    ->default(Venue::STATUS_AVAILABLE)
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