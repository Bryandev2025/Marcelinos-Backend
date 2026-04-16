<?php

namespace App\Filament\Resources\BedSpecifications\Schemas;

use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BedSpecificationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Bed specification')
                    ->description('Define a label you can assign to rooms (e.g. 1 Queen Bed, 2 Single Beds).')
                    ->icon('heroicon-o-moon')
                    ->schema([
                        TextInput::make('specification')
                            ->label('Specification')
                            ->placeholder('e.g. 1 Double Bed, 2 Single Beds')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->columnSpanFull(),
                    ]),
                Section::make('Images')
                    ->description('Images are attached to the bed specification (used by rooms that reference it).')
                    ->icon('heroicon-o-photo')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('featured_image')
                            ->collection('featured')
                            ->label('Featured Image')
                            ->disk('public')
                            ->image()
                            ->imagePreviewHeight('200')
                            ->required(fn ($record) => $record === null),
                        SpatieMediaLibraryFileUpload::make('gallery_images')
                            ->collection('gallery')
                            ->multiple()
                            ->label('Gallery Images')
                            ->disk('public')
                            ->image()
                            ->imagePreviewHeight('150'),
                    ]),
            ]);
    }
}
