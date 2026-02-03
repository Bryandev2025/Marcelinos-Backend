<?php

namespace App\Filament\Resources\Venues\RelationManagers;

use App\Models\Booking;
use App\Models\Guest;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class ReviewsRelationManager extends RelationManager
{
    protected static string $relationship = 'reviews';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Hidden::make('is_site_review')
                ->default(false),

            Select::make('guest_id')
                ->label('Guest')
                ->options(fn () => Guest::query()
                    ->select('id', 'first_name', 'middle_name', 'last_name')
                    ->get()
                    ->mapWithKeys(fn (Guest $guest) => [$guest->id => $guest->full_name])
                    ->all())
                ->searchable()
                ->required(),

            Select::make('booking_id')
                ->label('Booking')
                ->options(fn () => Booking::query()->pluck('reference_number', 'id')->all())
                ->searchable()
                ->nullable(),

            Select::make('rating')
                ->options([
                    1 => '1',
                    2 => '2',
                    3 => '3',
                    4 => '4',
                    5 => '5',
                ])
                ->required(),

            TextInput::make('title')
                ->maxLength(255),

            Textarea::make('comment')
                ->rows(4)
                ->columnSpanFull(),

            Toggle::make('is_approved')
                ->label('Approved'),

            DateTimePicker::make('reviewed_at')
                ->native(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('guest.full_name')
                    ->label('Guest')
                    ->searchable(),

                TextColumn::make('booking.reference_number')
                    ->label('Booking')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('rating')
                    ->badge(),

                ToggleColumn::make('is_approved')
                    ->label('Approved'),

                TextColumn::make('reviewed_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
