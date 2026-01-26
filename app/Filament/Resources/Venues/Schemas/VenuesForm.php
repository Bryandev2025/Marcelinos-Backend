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

                // ---------------- General Info ----------------
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

                // ---------------- Amenities ----------------
                Section::make('Amenities')
                    ->schema([
                        CheckboxList::make('amenities')
                            ->relationship('amenities', 'name')
                            ->columns(3),
                    ]),

                // ---------------- Media ----------------
                Section::make('Media')
                    ->schema([

                        // Main Featured Image
                        FileUpload::make('main_image')
                            ->label('Main Featured Image')
                            ->image()
                            ->disk('public')
                            ->directory('venues/main')
                            ->maxSize(2048)
                            ->required()
                            ->imagePreviewHeight('150')
                            ->dehydrated(false)
                            ->loadStateFromRelationshipsUsing(function ($record) {
                                $image = $record->mainImage;
                                if (!$image) return [];
                                return [
                                    [
                                        'id' => $image->id,
                                        'url' => asset('storage/' . $image->url), 
                                    ]
                                ];
                            })
                            ->saveRelationshipsUsing(function ($record, $state) {
                                if (!$state) return;

                                $record->mainImage()->delete();

                                // $state is an array of uploaded files
                                foreach ((array)$state as $file) {
                                    $path = str_replace(asset('storage/'), '', $file['url'] ?? $file);
                                    $record->images()->create([
                                        'url' => $path,
                                        'type' => 'main',
                                    ]);
                                }
                            }),

                        // Gallery Images
                        FileUpload::make('gallery_images')
                            ->label('Venue Gallery')
                            ->image()
                            ->multiple()
                            ->disk('public')
                            ->directory('venues/gallery')
                            ->maxSize(2048)
                            ->imagePreviewHeight('150')
                            ->dehydrated(false)
                            ->loadStateFromRelationshipsUsing(function ($record) {
                                return $record->gallery->map(fn($image) => [
                                    'id' => $image->id,
                                    'url' => asset('storage/' . $image->url),
                                ])->toArray();
                            })
                            ->saveRelationshipsUsing(function ($record, $state) {
                                $record->gallery()->delete();

                                if (!$state) return;

                                foreach ($state as $file) {
                                    $path = str_replace(asset('storage/'), '', $file['url'] ?? $file);
                                    $record->images()->create([
                                        'url' => $path,
                                        'type' => 'gallery',
                                    ]);
                                }
                            }),
                    ]),
            ]);
    }
}
                    
