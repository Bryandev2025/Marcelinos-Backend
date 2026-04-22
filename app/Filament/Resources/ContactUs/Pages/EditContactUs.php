<?php

namespace App\Filament\Resources\ContactUs\Pages;

use App\Filament\Actions\TypedDeleteAction;
use App\Filament\Actions\TypedForceDeleteAction;
use App\Filament\Resources\ContactUs\ContactUsResource;
use App\Models\ContactUs;
use Filament\Actions\Action;
use Filament\Actions\RestoreAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;

class EditContactUs extends EditRecord
{
    protected static string $resource = ContactUsResource::class;

    public function form(Schema $schema): Schema
    {
        return ContactUsResource::form($schema);
    }

    protected function getHeaderActions(): array
    {
        $openConversation = Action::make('openConversation')
            ->label('Open Conversation')
            ->icon('heroicon-o-chat-bubble-left-right')
            ->color('success')
            ->url(fn (ContactUs $record): string => ContactUsResource::getUrl(
                'conversation',
                ['record' => $record],
                panel: Filament::getCurrentPanel()?->getId() ?? 'admin',
            ));

        if ($this->record->trashed()) {
            return [
                $openConversation,
                RestoreAction::make(),
                TypedForceDeleteAction::make(fn (ContactUs $record): string => filled($record->email) ? $record->email : $record->full_name),
            ];
        }

        return [
            $openConversation,
            TypedDeleteAction::make(fn (ContactUs $record): string => filled($record->email) ? $record->email : $record->full_name),
        ];
    }
}
