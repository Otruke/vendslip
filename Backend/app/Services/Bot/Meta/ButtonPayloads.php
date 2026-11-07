<?php

declare(strict_types=1);

namespace App\Services\Bot\Meta;

/** Button / quick-reply ids shared by every Meta channel. They round-trip untouched in webhooks. */
final class ButtonPayloads
{
    public const string CONFIRM = 'NS_CONFIRM';

    public const string CONFIRM_CLEAN = 'NS_CONFIRM_CLEAN';

    public const string EDIT = 'NS_EDIT';

    public const string CANCEL = 'NS_CANCEL';

    /** Normalised BotOrchestrator action for a tapped payload, or null for anything else. */
    public static function action(?string $payload): ?string
    {
        return match ($payload) {
            self::CONFIRM => 'confirm',
            self::CONFIRM_CLEAN => 'confirm_clean',
            self::EDIT => 'edit',
            self::CANCEL => 'cancel',
            default => null,
        };
    }
}
