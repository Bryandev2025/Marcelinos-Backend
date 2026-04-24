<?php

namespace App\Filament\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Hash;

class Profile extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'profile';

    protected static ?string $title = 'Profile';

    protected string $view = 'filament.pages.profile';

    public string $name = '';

    public string $email = '';

    public string $currentPassword = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public function mount(): void
    {
        $user = auth()->user();

        $this->name = (string) ($user?->name ?? '');
        $this->email = (string) ($user?->email ?? '');
    }

    public function saveProfile(): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc,dns', 'max:255'],
        ]);

        $user->forceFill([
            'name' => $this->name,
            'email' => $this->email,
        ])->save();

        Notification::make()
            ->title('Profile saved')
            ->success()
            ->send();
    }

    public function changePassword(): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        $this->validate([
            'currentPassword' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'same:passwordConfirmation'],
            'passwordConfirmation' => ['required', 'string'],
        ]);

        if (! Hash::check($this->currentPassword, (string) $user->password)) {
            $this->addError('currentPassword', 'Current password is incorrect.');

            return;
        }

        $user->forceFill([
            'password' => $this->password,
        ])->save();

        $this->currentPassword = '';
        $this->password = '';
        $this->passwordConfirmation = '';

        Notification::make()
            ->title('Password updated')
            ->success()
            ->send();
    }
}

