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
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

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
                ->content('No checklist items are available for this room. Staff can still complete checkout and add notes when needed.')
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

                BadgeColumn::make('damage_count')
                    ->label('Damage / Missing')
                    ->getStateUsing(function (RoomChecklist $record): string {
                        $count = (int) $record->items()
                            ->whereIn('status', [
                                RoomChecklistItem::STATUS_BROKEN,
                                RoomChecklistItem::STATUS_MISSING,
                            ])
                            ->count();

                        return (string) $count;
                    })
                    ->color(fn (string $state): string => ((int) $state) > 0 ? 'danger' : 'success')
                    ->formatStateUsing(fn (string $state): string => ((int) $state) > 0 ? "{$state} issue(s)" : 'None'),

                TextColumn::make('items_inline')
                    ->label('Checklist details')
                    ->html()
                    ->wrap()
                    ->getStateUsing(function (RoomChecklist $record): HtmlString {
                        $items = $record->items()->get(['label', 'status', 'notes']);
                        if ($items->isEmpty()) {
                            return new HtmlString('<span class="text-gray-500">No checklist items configured.</span>');
                        }

                        $rows = $items->map(function (RoomChecklistItem $item): string {
                            $status = (string) ($item->status ?: RoomChecklistItem::STATUS_GOOD);
                            $statusLabel = match ($status) {
                                RoomChecklistItem::STATUS_BROKEN => 'Broken',
                                RoomChecklistItem::STATUS_MISSING => 'Missing',
                                default => 'Good',
                            };
                            $statusClass = match ($status) {
                                RoomChecklistItem::STATUS_BROKEN => 'text-amber-700 dark:text-amber-300',
                                RoomChecklistItem::STATUS_MISSING => 'text-red-700 dark:text-red-300',
                                default => 'text-emerald-700 dark:text-emerald-300',
                            };
                            $label = e((string) $item->label);
                            $notes = trim((string) ($item->notes ?? ''));
                            $notesHtml = $notes !== ''
                                ? '<div class="text-[11px] text-gray-500 dark:text-gray-400">'.e($notes).'</div>'
                                : '';

                            return '<div class="py-1">'
                                .'<div class="flex items-center justify-between gap-2">'
                                .'<span class="font-medium text-gray-800 dark:text-gray-100">'.$label.'</span>'
                                .'<span class="text-xs font-semibold '.$statusClass.'">'.$statusLabel.'</span>'
                                .'</div>'
                                .$notesHtml
                                .'</div>';
                        })->implode('');

                        return new HtmlString('<div class="space-y-1">'.$rows.'</div>');
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalHeading('Room condition checklist')
                    ->modalSubmitActionLabel('Save')
                    ->label('Update'),
            ]);
    }
}

