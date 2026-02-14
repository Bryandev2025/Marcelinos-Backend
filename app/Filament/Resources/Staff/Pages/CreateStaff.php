<?php

namespace App\Filament\Resources\Staff\Pages;

use App\Filament\Resources\Staff\StaffResource;
use App\Mail\StaffOtpVerification;
use App\Models\OtpVerification;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Filament\Notifications\Notification;

class CreateStaff extends CreateRecord
{
    protected static string $resource = StaffResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name')
                ->label('Full Name')
                ->required()
                ->maxLength(255),

            TextInput::make('email')
                ->label('Email Address')
                ->email()
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255),

            TextInput::make('password')
                ->label('Password')
                ->password()
                ->required()
                ->minLength(8)
                ->maxLength(255),

            TextInput::make('otp')
                ->label('Verification Code')
                ->numeric()
                ->length(6)
                ->helperText('Click "Send OTP" to receive a verification code via email.'),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendOtp')
                ->label('Send OTP')
                ->icon('heroicon-o-envelope')
                ->action(function () {
                    $email = $this->form->getState()['email'] ?? null;

                    if (!$email) {
                        Notification::make()
                            ->title('Email Required')
                            ->body('Please enter an email address first.')
                            ->warning()
                            ->send();
                        return;
                    }

                    $otp = OtpVerification::createForEmail($email);
                    Mail::to($email)->send(new StaffOtpVerification($otp->code));

                    Notification::make()
                        ->title('OTP Sent')
                        ->body('A verification code has been sent to ' . $email)
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['otp'])) {
            Notification::make()
                ->title('OTP Required')
                ->body('Please enter the verification code.')
                ->danger()
                ->send();

            $this->halt();
        }

        $otpRecord = OtpVerification::where('email', $data['email'])->first();

        if (!$otpRecord || !$otpRecord->verify($data['otp'])) {
            Notification::make()
                ->title('Invalid OTP')
                ->body('The verification code is invalid or expired.')
                ->danger()
                ->send();

            $this->halt();
        }

        unset($data['otp']);
        $data['password'] = Hash::make($data['password']);
        $data['role'] = 'staff';
        $data['is_active'] = true;

        return $data;
    }

    protected function afterCreate(): void
    {
        // Clean up OTP
        OtpVerification::where('email', $this->record->email)->delete();
    }
}
