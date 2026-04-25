<?php

namespace App\Filament\Resources\ContactUs\Tables;

use App\Filament\Actions\TypedDeleteBulkAction;
use App\Filament\Actions\TypedForceDeleteBulkAction;
use App\Filament\Resources\ContactUs\ContactUsResource;
use App\Models\ContactUs;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ContactUsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),

                TextColumn::make('subject')
                    ->label('Subject')
                    ->searchable(),

                TextColumn::make('latestMessage.body')
                    ->label('Latest Message')
                    ->limit(55)
                    ->placeholder('No messages yet'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'new' => 'gray',
                        'in_progress' => 'warning',
                        'resolved' => 'success',
                        'closed' => 'danger',
                    }),

                TextColumn::make('replied_at')
                    ->label('Replied At')
                    ->dateTime()
                    ->placeholder('Not replied yet')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Submitted At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'new' => 'New',
                        'in_progress' => 'In Progress',
                        'resolved' => 'Resolved',
                        'closed' => 'Closed',
                    ]),

                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('conversation')
                    ->label('Open Conversation')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->visible(fn (ContactUs $record): bool => $record->exists)
                    ->url(fn (ContactUs $record): string => ContactUsResource::getUrl(
                        'conversation',
                        ['record' => $record],
                        panel: Filament::getCurrentPanel()?->getId() ?? 'admin',
                    )),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    TypedDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    TypedForceDeleteBulkAction::make(),
                ]),
            ])
            ->recordClasses(fn (ContactUs $record): string => $record->status === 'new'
                ? 'fi-contact-us-new-row'
                : '')
            ->defaultSort('created_at', 'desc');
    }
}
