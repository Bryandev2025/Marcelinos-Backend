<?php

namespace App\Filament\Resources\Rooms\Schemas;

use App\Models\Image;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\CheckboxList;

class RoomForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Information')
                    ->schema([
                        TextInput::make('name')->required(),
                        TextInput::make('capacity')->required()->numeric(),
                        Select::make('type')
                            ->options(['standard' => 'Standard', 'family' => 'Family', 'deluxe' => 'Deluxe'])
                            ->required(),
                        TextInput::make('price')->required()->numeric()->prefix('₱'),
                        Select::make('status')
                            ->options(['available' => 'Available', 'occupied' => 'Occupied', 'cleaning' => 'Cleaning'])
                            ->default('available')->required(),
                    ])->columns(2),

                Section::make('Amenities')
                    ->schema([
                        CheckboxList::make('amenities')
                            ->relationship('amenities', 'name')
                            ->columns(3),
                    ]),

                Section::make('Media')
                    ->schema([
                        // MAIN IMAGE LOGIC
                        FileUpload::make('main_image')
                            ->label('Main Featured Image')
                            ->image()
                            ->directory('rooms/main')
                            // Load existing image from DB
                            ->loadStateFromRelationshipsUsing(static function (Model $record) {
                                return $record->mainImage?->url;
                            })
                            // Save to our custom images table
                            ->saveRelationshipsUsing(static function (Model $record, $state) {
                                if (!$state) return;
                                $record->mainImage()->updateOrCreate(
                                    ['type' => 'main'],
                                    ['url' => $state]
                                );
                            })->dehydrated(false),

                        // GALLERY LOGIC
                        FileUpload::make('gallery_images')
                            ->label('Room Gallery')
                            ->image()
                            ->multiple()
                            ->directory('rooms/gallery')
                            ->loadStateFromRelationshipsUsing(static function (Model $record) {
                                return $record->gallery()->pluck('url')->toArray();
                            })
                            ->saveRelationshipsUsing(static function (Model $record, $state) {
                                // Clear old gallery and save new ones
                                $record->gallery()->delete();
                                if (!$state) return;
                                foreach ($state as $url) {
                                    $record->images()->create([
                                        'url' => $url,
                                        'type' => 'gallery',
                                    ]);
                                }
                            })->dehydrated(false),
                    ]),
            ]);
    }
}