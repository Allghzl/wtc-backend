<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $verificationUrl,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify Your Email Address')
            ->greeting('Hi ' . $notifiable->name . '!')
            ->line('Thanks for signing up. Please verify your email address by clicking the button below.')
            ->action('Verify Email', $this->verificationUrl)
            ->line('This link will expire in ' . config('auth.verification.expire', 60) . ' minutes.')
            ->line('If you did not create an account, no further action is required.');
    }
}
