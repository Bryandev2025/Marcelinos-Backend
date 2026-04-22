<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApiResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $token,
        private readonly string $email,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    private function resetUrl(): string
    {
        $base = rtrim((string) config('app.frontend_url'), '/');
        $email = urlencode($this->email);
        $token = urlencode($this->token);

        return "{$base}/reset-password?token={$token}&email={$email}";
    }

    public function toMail(object $notifiable): MailMessage
    {
        $expire = (int) config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject('Reset Password Notification')
            ->line('You are receiving this email because we received a password reset request for your account.')
            ->action('Reset Password', $this->resetUrl())
            ->line("This password reset link will expire in {$expire} minutes.")
            ->line('If you did not request a password reset, no further action is required.');
    }
}

