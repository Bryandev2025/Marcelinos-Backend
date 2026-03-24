<?php

namespace App\Filament\Resources\Staff\Pages;

use App\Filament\Resources\Staff\StaffResource;
use App\Filament\Resources\Staff\Schemas\StaffForm;
use App\Mail\StaffOtpVerification;
use App\Models\OtpVerification;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Throwable;

class CreateStaff extends CreateRecord
{
    protected static string $resource = StaffResource::class;
    public ?string $otpSentTo = null;

    public function form(Schema $schema): Schema
    {
        return StaffForm::configure($schema, withOtp: true);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendOtp')
                ->label('Send OTP')
                ->icon('heroicon-o-envelope')
                ->color('info')
                ->tooltip('Send verification code to the entered email address.')
                ->action(function () {
                    $email = strtolower(trim((string) ($this->form->getState()['email'] ?? '')));

                    $validator = Validator::make(
                        ['email' => $email],
                        [
                            'email' => [
                                'required',
                                'email',
                                Rule::unique('users', 'email'),
                            ],
                        ]
                    );

                    if ($validator->fails()) {
                        Notification::make()
                            ->title('Valid Email Required')
                            ->body($validator->errors()->first('email'))
                            ->warning()
                            ->send();

                        return;
                    }

                    try {
                        $otp = OtpVerification::createForEmail($email);
                        Mail::mailer('smtp')->to($email)->send(new StaffOtpVerification($otp->code));
                        $this->otpSentTo = $email;

                        Log::info('Staff OTP sent', [
                            'email' => $email,
                        ]);

                        Notification::make()
                            ->title('OTP Sent')
                            ->body('A verification code has been sent to ' . $email)
                            ->success()
                            ->send();
                    } catch (Throwable $exception) {
                        Log::error('Primary OTP send failed', [
                            'email' => $email,
                            'error' => $exception->getMessage(),
                        ]);

                        try {
                            $otp ??= OtpVerification::createForEmail($email);

                            Mail::mailer('smtp')->raw(
                                "Your verification code is {$otp->code}. This code expires in 10 minutes.",
                                function ($message) use ($email): void {
                                    $message->to($email)->subject('Staff Account Verification Code');
                                }
                            );

                            $this->otpSentTo = $email;

                            Notification::make()
                                ->title('OTP Sent (Fallback)')
                                ->body('OTP was sent using fallback delivery to ' . $email)
                                ->warning()
                                ->send();
                        } catch (Throwable $fallbackException) {
                            Log::error('Fallback OTP send failed', [
                                'email' => $email,
                                'error' => $fallbackException->getMessage(),
                            ]);

                            Notification::make()
                                ->title('Failed to Send OTP')
                                ->body('Could not deliver OTP. Please try again or check Mailtrap logs.')
                                ->danger()
                                ->send();
                        }
                    }
                }),
        ];
    }

    public function canCreateAnother(): bool
    {
        return false;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $email = strtolower(trim((string) ($data['email'] ?? '')));

        if (! $this->otpSentTo || $this->otpSentTo !== $email) {
            Notification::make()
                ->title('OTP Not Sent')
                ->body('Please click "Send OTP" after entering the final email address.')
                ->danger()
                ->send();

            $this->halt();
        }

        if (empty($data['otp'])) {
            Notification::make()
                ->title('OTP Required')
                ->body('Please enter the verification code.')
                ->danger()
                ->send();

            $this->halt();
        }

        $otpRecord = OtpVerification::where('email', $email)->first();

        if (!$otpRecord || !$otpRecord->verify($data['otp'])) {
            Notification::make()
                ->title('Invalid OTP')
                ->body('The verification code is invalid or expired.')
                ->danger()
                ->send();

            $this->halt();
        }

        unset($data['otp']);
        $data['email'] = $email;
        $data['role'] = 'staff';
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        return $data;
    }

    protected function afterCreate(): void
    {
        // Clean up OTP
        OtpVerification::where('email', $this->record->email)->delete();
    }
}
