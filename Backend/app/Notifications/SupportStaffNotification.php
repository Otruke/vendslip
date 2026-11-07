<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells support staff about a new ticket or a customer's reply. */
final class SupportStaffNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $reference,
        public readonly string $title,
        public readonly string $merchantName,
        public readonly string $preview,
        public readonly string $url,
    ) {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("[{$this->reference}] {$this->title}")
            ->greeting($this->title)
            ->line("Business: {$this->merchantName}")
            ->line('“'.$this->preview.'”')
            ->action('Open the ticket', $this->url);
    }
}
