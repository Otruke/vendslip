<?php

declare(strict_types=1);

namespace App\Services\Bot;

use App\Support\Urls;

/** Short, human bot copy (Paystack-style: punchy, no jargon). */
final class BotTexts
{
    public static function help(): string
    {
        return implode("\n", [
            'NetSlip turns chat orders into receipts.',
            '',
            'Send fields one by one or all together:',
            '/name = Ada Obi',
            '/item = 2x Sneakers @ 15000',
            '/amount = 30000  (instead of items)',
            '/type = invoice  (default: receipt)',
            '',
            'Optional: /phone /address /discount /shipping /vat /paid /due /note',
            '',
            'Then: /confirm · /edit · /cancel · /preview',
            '/confirm clean  — spend 1 credit, no watermark',
            '/credits  — see your balance',
        ]);
    }

    public static function notLinked(): string
    {
        return "Welcome to NetSlip!\nLink this chat first: Dashboard → Channels → get a code, then send:\n/link YOURCODE\n\n".Urls::frontend('dashboard/channels');
    }

    public static function linked(string $merchant, string $name): string
    {
        return "Linked ✅ {$name} can now send slips for {$merchant}.\nSend /help to see how.";
    }

    public static function editing(): string
    {
        return "Editing. Send only what changes, e.g.\n/amount = 20000\nThen /confirm.";
    }

    public static function cancelled(): string
    {
        return 'Draft cancelled. Start again any time with /name = …';
    }

    public static function cleared(): string
    {
        return 'Draft cleared. Send /name = … to start a new slip.';
    }

    /** @param list<string> $missing */
    public static function missing(array $missing): string
    {
        return 'Almost there. Still need: '.implode(', ', $missing).'.';
    }
}
