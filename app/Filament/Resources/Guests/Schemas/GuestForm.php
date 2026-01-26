<?php

namespace App\Filament\Resources\Guests\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;

class GuestForm
{
     public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('first_name')->required(),
                TextInput::make('middle_name'),
                TextInput::make('last_name')->required(),
                TextInput::make('contact_num')->required(),
                TextInput::make('email')->required()->email(),
                Select::make('gender')->options(['male'=>'Male','female'=>'Female','other'=>'Other'])->required(),
                Select::make('is_international')->options([0=>'No',1=>'Yes'])->default(0),
                TextInput::make('country')->default('Philippines'),
                TextInput::make('province'),
                TextInput::make('municipality'),
                TextInput::make('barangay'),
                TextInput::make('city'),
                TextInput::make('zip_code'),
                SpatieMediaLibraryFileUpload::make('id_verification')
                    ->collection('id_verification')
                    ->label('ID Verification')
                    ->image()
                    ->required(),
            ]);
    }
}
