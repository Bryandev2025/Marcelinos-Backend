<?php

namespace App\Filament\Pages\Auth;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Actions\Action;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\MultiFactor\Contracts\HasBeforeChallengeHook;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Models\Contracts\FilamentUser;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\RateLimiter;

class Login extends BaseLogin
{
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getRememberFormComponent(),
        ]);
    }

    protected function getFormActions(): array
    {
        return [
            $this->getAuthenticateFormAction(),
        ];
    }

    public function getFormContentComponent(): Component
    {
        $footer = [
            Actions::make($this->getFormActions())
                ->alignment($this->getFormActionsAlignment())
                ->fullWidth($this->hasFullWidthFormActions())
                ->key('form-actions'),
        ];

        if (filament()->hasPasswordReset()) {
            $footer[] = Actions::make([
                $this->getRequestPasswordResetFormAction(),
            ])
                ->alignment(Alignment::Center)
                ->key('request-password-reset-action');
        }

        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('authenticate')
            ->footer($footer)
            ->visible(fn (): bool => blank($this->userUndertakingMultiFactorAuthentication));
    }

    protected function getRequestPasswordResetFormAction(): Action
    {
        return Action::make('requestPasswordReset')
            ->link()
            ->label(__('filament-panels::auth/pages/login.actions.request_password_reset.label'))
            ->url(filament()->getRequestPasswordResetUrl());
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label(__('filament-panels::auth/pages/login.form.password.label'))
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->autocomplete('current-password')
            ->required();
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();

        $failedKey = $this->failedLoginRateLimiterKey($data['email'] ?? '');

        if (RateLimiter::tooManyAttempts($failedKey, $this->maxFailedLoginAttempts())) {
            $this->getRateLimitedNotification(new TooManyRequestsException(
                static::class,
                'authenticate',
                request()->ip(),
                RateLimiter::availableIn($failedKey),
            ))?->send();

            return null;
        }

        return $this->authenticateWithCredentials($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function authenticateWithCredentials(array $data): ?LoginResponse
    {
        /** @var SessionGuard $authGuard */
        $authGuard = Filament::auth();

        $authProvider = $authGuard->getProvider(); /** @phpstan-ignore-line */
        $credentials = $this->getCredentialsFromFormData($data);

        $user = $authProvider->retrieveByCredentials($credentials);

        if ((! $user) || (! $authProvider->validateCredentials($user, $credentials))) {
            $this->userUndertakingMultiFactorAuthentication = null;

            $this->fireFailedEvent($authGuard, $user, $credentials);
            $this->throwFailureValidationException();
        }

        if (
            filled($this->userUndertakingMultiFactorAuthentication) &&
            (decrypt($this->userUndertakingMultiFactorAuthentication) === $user->getAuthIdentifier())
        ) {
            if ($this->isMultiFactorChallengeRateLimited($user)) {
                return null;
            }

            $this->multiFactorChallengeForm->validate();
        } else {
            foreach (Filament::getMultiFactorAuthenticationProviders() as $multiFactorAuthenticationProvider) {
                if (! $multiFactorAuthenticationProvider->isEnabled($user)) {
                    continue;
                }

                $this->userUndertakingMultiFactorAuthentication = encrypt($user->getAuthIdentifier());

                if ($multiFactorAuthenticationProvider instanceof HasBeforeChallengeHook) {
                    $multiFactorAuthenticationProvider->beforeChallenge($user);
                }

                break;
            }

            if (filled($this->userUndertakingMultiFactorAuthentication)) {
                $this->multiFactorChallengeForm->fill();

                return null;
            }
        }

        if (! $authGuard->attemptWhen($credentials, function (Authenticatable $user): bool {
            if (! ($user instanceof FilamentUser)) {
                return true;
            }

            return $user->canAccessPanel(Filament::getCurrentOrDefaultPanel());
        }, $data['remember'] ?? false)) {
            $this->fireFailedEvent($authGuard, $user, $credentials);
            $this->throwFailureValidationException();
        }

        session()->regenerate();

        RateLimiter::clear($this->failedLoginRateLimiterKey($data['email'] ?? ''));

        return app(LoginResponse::class);
    }

    protected function throwFailureValidationException(): never
    {
        $data = $this->form->getState();
        RateLimiter::hit(
            $this->failedLoginRateLimiterKey($data['email'] ?? ''),
            $this->failedLoginDecaySeconds(),
        );

        parent::throwFailureValidationException();
    }

    protected function failedLoginRateLimiterKey(?string $email): string
    {
        $normalized = strtolower(trim((string) $email));

        return 'filament-login-failed:'.sha1($normalized.'|'.request()->ip());
    }

    protected function maxFailedLoginAttempts(): int
    {
        return max(1, (int) config('login.max_attempts', 5));
    }

    protected function failedLoginDecaySeconds(): int
    {
        return max(60, (int) config('login.decay_seconds', 900));
    }

    protected function getRateLimitedNotification(TooManyRequestsException $exception): ?Notification
    {
        $seconds = max(0, (int) $exception->secondsUntilAvailable);

        if ($seconds <= 60) {
            $body = __('login_throttle.body_seconds', ['seconds' => $seconds]);
        } else {
            $minutes = max(1, (int) ceil($seconds / 60));
            $body = __('login_throttle.body_minutes', ['minutes' => $minutes]);
        }

        return Notification::make()
            ->title(__('login_throttle.title'))
            ->body($body)
            ->danger();
    }
}
