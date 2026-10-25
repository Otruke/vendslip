<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class StaffInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $merchantName,
        private readonly string $roleLabel,
        private readonly string $url,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("You're invited to {$this->merchantName} on NetSlip")
            ->greeting('Hello!')
            ->line("{$this->merchantName} added you as {$this->roleLabel}.")
            ->action('Accept invitation', $this->url)
            ->line('This link expires in 7 days.');
    }
}
