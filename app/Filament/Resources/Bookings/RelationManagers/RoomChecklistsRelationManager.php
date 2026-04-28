<?php

namespace App\Filament\Resources\Bookings\RelationManagers;

use App\Models\RoomChecklist;
use App\Models\RoomChecklistItem;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RoomChecklistsRelationManager extends RelationManager
{
    protected static string $relationship = 'roomChecklists';

    protected static ?string $title = 'Room condition checklist';

    protected static ?string $recordTitleAttribute = 'room_id';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('room.name')
                ->label('Room')
                ->disabled()
                ->dehydrated(false),

            DateTimePicker::make('generated_at')
                ->native(false)
                ->disabled()
                ->dehydrated(false),

            DateTimePicker::make('completed_at')
                ->label('Checklist completed at')
                ->native(false)
                ->helperText('Optional: set when inspection is finished.'),

            Repeater::make('items')
                ->relationship('items')
                ->label('Checklist items')
                ->defaultItems(0)
                ->reorderable(false)
                ->schema([
                    TextInput::make('label')
                        ->disabled()
                        ->dehydrated(false),

                    TextInput::make('charge')
                        ->label('Charge')
                        ->disabled()
                        ->dehydrated(false),

                    Select::make('status')
                        ->options([
                            RoomChecklistItem::STATUS_GOOD => 'Good',
                            RoomChecklistItem::STATUS_BROKEN => 'Broken',
                            RoomChecklistItem::STATUS_MISSING => 'Missing',
                        ])
                        ->required(),

                    Textarea::make('notes')
                        ->rows(2)
                        ->columnSpanFull(),
                ])
                ->columns(3)
                ->itemLabel(fn (array $state): ?string => $state['label'] ?? null),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitle(fn (RoomChecklist $record): string => $record->room?->name ?? 'Room checklist')
            ->columns([
                TextColumn::make('room.name')
                    ->label('Room')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('generated_at')
                    ->label('Generated')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('completed_at')
                    ->label('Completed')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalHeading('Room condition checklist')
                    ->modalSubmitActionLabel('Save'),
            ]);
    }
}

