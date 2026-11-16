<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StudentInvitation extends Notification
{
    public function __construct(private readonly string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Activate your School DMS student account')
            ->line('Your school has provisioned a student account. Use your student ID and this link to set your password.')
            ->action('Activate account', route('register', ['token' => $this->token, 'email' => $notifiable->email]))
            ->line('This single-use link expires in ' . config('auth.passwords.users.expire', 60) . ' minutes. Request another invitation from your school if it expires.');
    }
}
