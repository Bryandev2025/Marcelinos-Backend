<?php

namespace App\Filament\Resources\ContactUs\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ContactUsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('full_name')
                    ->label('Full Name')
                    ->disabled()
                    ->required(),

                TextInput::make('email')
                    ->label('Email')
                    ->disabled()
                    ->required(),

                TextInput::make('phone')
                    ->label('Phone')
                    ->disabled(),

                TextInput::make('subject')
                    ->label('Subject')
                    ->disabled()
                    ->required(),

                Textarea::make('message')
                    ->label('Message')
                    ->disabled()
                    ->rows(4)
                    ->required(),

                Select::make('status')
                    ->label('Status')
                    ->options([
                        'new' => 'New',
                        'in_progress' => 'In Progress',
                        'resolved' => 'Resolved',
                        'closed' => 'Closed',
                    ])
                    ->default('new')
                    ->required(),
            ]);
    }
}
