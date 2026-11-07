<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A friendly due-date reminder to the business's customer, sent on the business's behalf. */
final class InvoiceReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $stage,
        public readonly string $merchantName,
        public readonly ?string $merchantEmail,
        public readonly ?string $merchantPhone,
        public readonly string $customerName,
        public readonly string $docNumber,
        public readonly string $balance,
        public readonly string $dueDate,
        public readonly int $days,
        public readonly string $payUrl,
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
        [$subject, $lead] = match (true) {
            $this->stage === 'BEFORE_DUE' => [
                "Reminder: invoice {$this->docNumber} is due on {$this->dueDate}",
                "This is a friendly reminder that invoice {$this->docNumber} from {$this->merchantName} is due in {$this->days} ".($this->days === 1 ? 'day' : 'days')." ({$this->dueDate}).",
            ],
            $this->stage === 'DUE_TODAY' => [
                "Invoice {$this->docNumber} is due today",
                "Invoice {$this->docNumber} from {$this->merchantName} is due today.",
            ],
            default => [
                "Overdue: invoice {$this->docNumber} from {$this->merchantName}",
                "Invoice {$this->docNumber} from {$this->merchantName} was due on {$this->dueDate} and still has a balance.",
            ],
        };

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting("Hello {$this->customerName},")
            ->line($lead)
            ->line("Balance due: **{$this->balance}**")
            ->action('View and pay the invoice', $this->payUrl)
            ->line('If you have already paid, thank you, and please ignore this reminder.');
        $contact = array_filter([$this->merchantEmail, $this->merchantPhone]);
        if ($contact !== []) {
            $mail->line("Questions? Contact {$this->merchantName}: ".implode(' · ', $contact).'.');
        }
        if ($this->merchantEmail !== null && filter_var($this->merchantEmail, FILTER_VALIDATE_EMAIL) !== false) {
            $mail->replyTo($this->merchantEmail, $this->merchantName);
        }

        return $mail->salutation("— {$this->merchantName}, via VendSlip");
    }
}
