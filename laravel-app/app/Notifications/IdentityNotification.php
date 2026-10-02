<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class IdentityNotification extends Notification
{
    public function __construct(public string $type, public string $url) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        [$subject, $text, $button] = match ($this->type) {
            'email.verification' => ['Verify your NestCrafters email', 'Confirm your email address to continue. Email verification does not replace contractor approval.', 'Verify email'],
            'password.recovery' => ['Reset your NestCrafters password', 'Use this link to choose a new password. It expires in 60 minutes. If you did not request it, you can ignore this message.', 'Reset password'],
            default => ['New contractor awaiting review', 'A new contractor account is awaiting administrator review.', 'Review accounts'],
        };

        return (new MailMessage)->subject($subject)->greeting('Hello,')->line($text)->action($button, $this->url);
    }
}
