<?php

namespace App\Filament\Resources\Galleries\Tables;

use App\Filament\Resources\Galleries\GalleryResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;

class GalleriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->extraAttributes([
                'class' => 'gallery-table-flat',
            ])
            ->columns([])
            ->content(view('filament.tables.content.galleries-grid'))
            ->defaultSort('created_at', 'desc')
            ->paginated(false)
            ->recordUrl(fn ($record): string => GalleryResource::getUrl('edit', ['record' => $record]))
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Edit image')
                    ->extraAttributes(['class' => 'hidden']),
                DeleteAction::make()
                    ->label('Delete image')
                    ->modalHeading('Delete image')
                    ->successNotificationTitle('Image deleted')
                    ->extraAttributes(['class' => 'hidden']),
            ]);
    }
}
