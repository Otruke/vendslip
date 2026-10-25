<?php

declare(strict_types=1);

namespace NetSlip\Bots\Instagram;

use App\Services\Bot\Actions\AskConfirmation;
use App\Services\Bot\Actions\DeliverSlip;
use App\Services\Bot\SummaryFormatter;
use App\Services\Bot\Twilio\TwiML;
use Illuminate\Http\Response;

final class InstagramPayloadBuilder
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config) {}

    public function confirmation(AskConfirmation $action): Response
    {
        $text = SummaryFormatter::text($action, true);
        $max = (int) ($this->config['max_summary_chars'] ?? 1000);

        return TwiML::message(mb_strlen($text) > $max ? mb_substr($text, 0, $max - 1).'…' : $text);
    }

    public function slip(DeliverSlip $action): Response
    {
        return TwiML::message($action->caption."\n\n📄 Download slip: ".$action->pdfUrl);
    }
}
