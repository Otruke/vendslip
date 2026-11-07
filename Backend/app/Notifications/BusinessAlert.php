<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Services\Notifications\AlertCatalog;
use App\Support\Urls;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One business alert. The in-app copy is written straight away (the bell updates instantly); the
 * email goes through the queue, so a slow mail server never slows down a sale or a webhook.
 */
final class BusinessAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $event,
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $path = null,
        public readonly bool $email = false,
        public readonly ?string $merchantName = null,
    ) {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->email ? ['database', 'mail'] : ['database'];
    }

    /** @return array<string, string> */
    public function viaConnections(): array
    {
        return ['database' => 'sync'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return ['event' => $this->event, 'title' => $this->title, 'body' => $this->body, 'path' => $this->path];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(($this->merchantName !== null ? "{$this->merchantName}: " : '').$this->title)
            ->greeting($this->title)
            ->line($this->body);
        if ($this->path !== null) {
            $mail->action('Open VendSlip', Urls::frontend(ltrim($this->path, '/')));
        }

        return $mail->line($this->event === AlertCatalog::TICKET_UPDATE
            ? 'You get this because you opened this support ticket.'
            : 'You get this because of your alert settings in VendSlip (Settings → Alerts).');
    }
}
