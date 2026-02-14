<?php

namespace App\Filament\Resources\Galleries\Schemas;

use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Schemas\Schema;

class GalleryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                SpatieMediaLibraryFileUpload::make('image')
                    ->collection('image')
                    ->label('Gallery Image')
                    ->image()
                    ->required(),
            ]);
    }
}
