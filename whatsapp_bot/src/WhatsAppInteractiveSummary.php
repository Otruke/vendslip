<?php

declare(strict_types=1);

namespace NetSlip\Bots\WhatsApp;

use App\Services\Bot\Actions\AskConfirmation;
use App\Services\Bot\SummaryFormatter;

/**
 * Builds the single consolidated pre-confirmation message. With a Twilio Content quick-reply
 * template the vendor gets tappable [✅ Confirm] [✏️ Edit] [❌ Cancel]; without one, a numbered prompt.
 */
final class WhatsAppInteractiveSummary
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config) {}

    public function body(AskConfirmation $action, bool $numbered): string
    {
        $text = SummaryFormatter::text($action, $numbered);
        $max = (int) ($this->config['max_summary_chars'] ?? 1000);

        return mb_strlen($text) > $max ? mb_substr($text, 0, $max - 1).'…' : $text;
    }

    /** contentVariables JSON for a template whose body is just "{{1}}". */
    public function contentVariables(AskConfirmation $action): string
    {
        return (string) json_encode(['1' => $this->body($action, false)], JSON_UNESCAPED_UNICODE);
    }
}
