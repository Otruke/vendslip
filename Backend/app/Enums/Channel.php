<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum Channel: string
{
    use EnumHelpers;

    case WHATSAPP = 'WHATSAPP';
    case TELEGRAM = 'TELEGRAM';
    case INSTAGRAM = 'INSTAGRAM';
    case FACEBOOK = 'FACEBOOK';
    case WEB = 'WEB';

    public function label(): string
    {
        return match ($this) {
            self::WHATSAPP => 'WhatsApp',
            self::TELEGRAM => 'Telegram',
            self::INSTAGRAM => 'Instagram',
            self::FACEBOOK => 'Facebook Messenger',
            self::WEB => 'Web',
        };
    }

    /** Channels delivered through Twilio Programmable Messaging. */
    public function isTwilio(): bool
    {
        return in_array($this, [self::WHATSAPP, self::INSTAGRAM, self::FACEBOOK], true);
    }
}
