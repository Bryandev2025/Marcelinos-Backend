<?php

namespace App\Filament\Resources\Venues\Schemas;

use App\Models\Venue;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                Textarea::make('description')
                    ->label('Description')
                    ->rows(3)
                    ->columnSpanFull()
                    ->nullable()
                    ->reactive()
                    ->maxLength(400) // safety limit
                    ->afterStateUpdated(function ($state, callable $set) {
                        // Split words by space (faster than regex)
                        $words = array_filter(explode(' ', trim($state ?? '')));

                        // Hard stop at 50 words
                        if (count($words) > 50) {
                            $set('description', implode(' ', array_slice($words, 0, 50)));
                        }
                    })
                    ->helperText(function ($state) {
                        $count = count(array_filter(explode(' ', trim($state ?? ''))));

                        return "{$count}/50 words";
                    })
                    ->rules([
                        function ($attribute, $value, $fail) {
                            if (blank($value)) {
                                return;
                            }

                            $words = array_filter(explode(' ', trim($value)));
                            if (count($words) > 50) {
                                $fail('Description must not exceed 50 words.');
                            }
                        },
                    ]),
                TextInput::make('capacity')
                    ->required()
                    ->numeric(),

                TextInput::make('wedding_price')
                    ->label('Wedding (per event)')
                    ->required()
                    ->numeric()
                    ->prefix('₱')
                    ->default(8000),

                TextInput::make('birthday_price')
                    ->label('Birthday (per event)')
                    ->required()
                    ->numeric()
                    ->prefix('₱')
                    ->default(8000),

                TextInput::make('meeting_staff_price')
                    ->label('Meeting/Seminar (per event)')
                    ->required()
                    ->numeric()
                    ->prefix('₱')
                    ->default(8000),

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
                CheckboxList::make('amenities')
                    ->label('Amenities')
                    ->relationship(
                        titleAttribute: 'name',
                        modifyQueryUsing: fn ($query) => $query->orderBy('name'),
                    )
                    ->columns(2)
                    ->searchable()
                    ->bulkToggleable()
                    ->helperText('Check the amenities available at this venue. If none appear, add amenities in Properties → Amenities first.'),
            ]);
    }
}
