<?php

namespace App\Filament\Resources\Bookings\RelationManagers;

use App\Models\RoomChecklist;
use App\Models\RoomChecklistItem;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
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

            Placeholder::make('checklist_empty_state')
                ->label('Setup reminder')
                ->content('No damage checklist properties are assigned to this room yet. Add them in Properties -> Rooms -> Edit Room -> Damage checklist properties so staff can inspect damaged/missing items here.')
                ->visible(fn (?RoomChecklist $record): bool => (int) ($record?->items()->count() ?? 0) === 0),

            Repeater::make('items')
                ->relationship('items')
                ->label('Checklist items')
                ->defaultItems(0)
                ->reorderable(false)
                ->helperText('Mark only damaged/missing items and add notes only when needed. "Good" is the default healthy state.')
                ->schema([
                    TextInput::make('label')
                        ->label('Property')
                        ->disabled()
                        ->dehydrated(false),

                    TextInput::make('charge')
                        ->label('Charge')
                        ->disabled()
                        ->dehydrated(false),

                    ToggleButtons::make('status')
                        ->label('Condition')
                        ->options([
                            RoomChecklistItem::STATUS_GOOD => 'Good',
                            RoomChecklistItem::STATUS_BROKEN => 'Broken',
                            RoomChecklistItem::STATUS_MISSING => 'Missing',
                        ])
                        ->colors([
                            RoomChecklistItem::STATUS_GOOD => 'success',
                            RoomChecklistItem::STATUS_BROKEN => 'warning',
                            RoomChecklistItem::STATUS_MISSING => 'danger',
                        ])
                        ->icons([
                            RoomChecklistItem::STATUS_GOOD => 'heroicon-o-check-circle',
                            RoomChecklistItem::STATUS_BROKEN => 'heroicon-o-wrench-screwdriver',
                            RoomChecklistItem::STATUS_MISSING => 'heroicon-o-exclamation-triangle',
                        ])
                        ->default(RoomChecklistItem::STATUS_GOOD)
                        ->inline()
                        ->required(),

                    Textarea::make('notes')
                        ->label('Damage notes')
                        ->rows(2)
                        ->placeholder('Add details only when broken or missing (e.g., cracked screen, missing remote).')
                        ->visible(fn (callable $get): bool => in_array((string) $get('status'), [
                            RoomChecklistItem::STATUS_BROKEN,
                            RoomChecklistItem::STATUS_MISSING,
                        ], true))
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

