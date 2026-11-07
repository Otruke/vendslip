<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** One-time code the business owner gives VendSlip support so they can open the dashboard. */
final class SupportAccessCodeNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $code,
        private readonly string $merchantName,
        private readonly string $adminName,
        private readonly int $codeMinutes,
        private readonly int $sessionMinutes,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("VendSlip support access code for {$this->merchantName}: {$this->code}")
            ->greeting('Hello,')
            ->line("{$this->adminName} from VendSlip support has asked to open the {$this->merchantName} dashboard to help with your account.")
            ->line("Only share this code if you asked VendSlip for help: **{$this->code}**")
            ->line("It expires in {$this->codeMinutes} minutes. Once used, support can view and work in your dashboard for up to {$this->sessionMinutes} minutes. They can't change your password, sign-in security, team access or linked chats.")
            ->line("If you didn't ask for help, ignore this email: without the code nobody can get in. Every support visit is recorded in your account's audit log.");
    }
}
