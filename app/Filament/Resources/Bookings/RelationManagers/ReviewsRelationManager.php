<?php

namespace App\Filament\Resources\Bookings\RelationManagers;

use App\Models\Guest;
use App\Models\Room;
use App\Models\Venue;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ReviewsRelationManager extends RelationManager
{
    protected static string $relationship = 'reviews';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('guest_id')
                ->label('Guest')
                ->options(fn () => Guest::query()
                    ->select('id', 'first_name', 'middle_name', 'last_name')
                    ->get()
                    ->mapWithKeys(fn (Guest $guest) => [$guest->id => $guest->full_name])
                    ->all())
                ->searchable()
                ->default(fn ($livewire) => $livewire->ownerRecord->guest_id)
                ->required(),

            Toggle::make('is_site_review')
                ->label('Site Review')
                ->default(false)
                ->live()
                ->afterStateUpdated(function (Set $set, $state): void {
                    if ($state) {
                        $set('reviewable_type', null);
                        $set('reviewable_id', null);
                    }
                }),

            Select::make('reviewable_type')
                ->label('Review Type')
                ->options([
                    Room::class => 'Room',
                    Venue::class => 'Venue',
                ])
                ->searchable()
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('reviewable_id', null))
                ->required(fn (Get $get) => ! $get('is_site_review'))
                ->visible(fn (Get $get) => ! $get('is_site_review')),

            Select::make('reviewable_id')
                ->label('Review Target')
                ->options(function (Get $get): array {
                    return match ($get('reviewable_type')) {
                        Room::class => Room::query()->pluck('name', 'id')->all(),
                        Venue::class => Venue::query()->pluck('name', 'id')->all(),
                        default => [],
                    };
                })
                ->searchable()
                ->preload()
                ->required(fn (Get $get) => ! $get('is_site_review'))
                ->visible(fn (Get $get) => ! $get('is_site_review')),

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
                ->label('Approved')
                ->default(false),

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

                TextColumn::make('reviewable.name')
                    ->label('Target')
                    ->formatStateUsing(fn ($state, $record) => $record->is_site_review ? 'Site' : ($state ?? '')),

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
            ->filters([
                TernaryFilter::make('is_site_review')
                    ->label('Site Review')
                    ->trueLabel('Site')
                    ->falseLabel('Room/Venue'),

                TernaryFilter::make('is_approved')
                    ->label('Approved')
                    ->trueLabel('Approved')
                    ->falseLabel('Pending'),

                SelectFilter::make('rating')
                    ->options([
                        1 => '1',
                        2 => '2',
                        3 => '3',
                        4 => '4',
                        5 => '5',
                    ]),
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
