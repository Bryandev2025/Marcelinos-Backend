<?php

namespace App\Filament\Resources\Rooms\Schemas;

use App\Models\Room;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RoomForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required(),
                // Textarea::make('description')
                //     ->rows(3)
                //     ->columnSpanFull()
                //     ->nullable()
                //     ->maxLength(400)
                //     ->helperText('Maximum 50 words.'),
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
                TextInput::make('capacity')->required()->numeric(),
                Select::make('bedSpecifications') // matches the relationship name
                    ->label('Bed Specifications')
                    ->relationship('bedSpecifications', 'specification')
                    ->multiple()
                    ->required()
                    ->minItems(1)
                    ->searchable()
                    ->preload()
                    ->helperText('Required. Add options under Properties → Rooms → Specifications.'),
                Select::make('type')
                    ->options(Room::typeOptions())
                    ->required(),
                TextInput::make('price')->required()->numeric()->prefix('₱'),
                Select::make('status')
                    ->options(Room::statusOptions())
                    ->default(Room::STATUS_AVAILABLE)
                    ->required(),
                CheckboxList::make('amenities')
                    ->label('Amenities')
                    ->relationship(
                        titleAttribute: 'name',
                        modifyQueryUsing: fn ($query) => $query->orderBy('name'),
                    )
                    ->columns(2)
                    ->searchable()
                    ->bulkToggleable()
                    ->helperText('Check the amenities available in this room. If none appear, add amenities in Properties → Amenities first.'),
                CheckboxList::make('damageProperties')
                    ->label('Damage checklist properties')
                    ->relationship(
                        titleAttribute: 'name',
                        modifyQueryUsing: fn ($query) => $query->where('is_active', true)->orderBy('name'),
                    )
                    ->columns(2)
                    ->searchable()
                    ->bulkToggleable()
                    ->helperText('Optional. Manually select damage properties that apply to this room. Manage the list in Properties → Damage Properties.'),
            ]);
    }
}
