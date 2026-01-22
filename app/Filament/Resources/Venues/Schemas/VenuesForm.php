<?php

namespace App\Filament\Resources\Venues\Schemas;

use App\Models\Image;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Model;

class VenuesForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Information')
                    ->schema([
                        TextInput::make('name')
                            ->label('Venue Name')
                            ->required(),

                        Textarea::make('description')
                            ->label('Description'),

                        TextInput::make('capacity')
                            ->label('Capacity')
                            ->numeric()
                            ->required(),

                        TextInput::make('price')
                            ->label('Price')
                            ->numeric()
                            ->required()
                            ->prefix('₱'),
                    ])
                    ->columns(2),

                Section::make('Amenities')
                    ->schema([
                        CheckboxList::make('amenities')
                            ->relationship('amenities', 'name')
                            ->columns(3),
                    ]),

                Section::make('Media')
                    ->schema([
                        // MAIN IMAGE
                        FileUpload::make('main_image')
                            ->label('Main Featured Image')
                            ->image()
                            ->directory('venues/main')
                            ->disk('public')
                            ->loadStateFromRelationshipsUsing(static function (Model $record) {
                                return $record->mainImage?->url;
                            })
                            ->saveRelationshipsUsing(static function (Model $record, $state) {
                                if (!$state) return;
                                $record->mainImage()->updateOrCreate(
                                    ['type' => 'main'],
                                    ['url' => 'venues/main/' . basename($state)]
                                );
                            })->dehydrated(false),

                        // GALLERY IMAGES
                        FileUpload::make('gallery_images')
                            ->label('Venue Gallery')
                            ->image()
                            ->multiple()
                            ->directory('venues/gallery')
                            ->disk('public')
                            ->loadStateFromRelationshipsUsing(static function (Model $record) {
                                return $record->gallery()->pluck('url')->toArray();
                            })
                            ->saveRelationshipsUsing(static function (Model $record, $state) {
                                $record->gallery()->delete();
                                if (!$state) return;
                                foreach ($state as $url) {
                                    $record->images()->create([
                                        'url' => 'venues/gallery/' . basename($url),
                                        'type' => 'gallery',
                                    ]);
                                }
                            })->dehydrated(false),
                    ]),
            ]);
    }
}
