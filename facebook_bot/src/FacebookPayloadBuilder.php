<?php

declare(strict_types=1);

namespace NetSlip\Bots\Facebook;

use App\Services\Bot\Actions\AskConfirmation;
use App\Services\Bot\Actions\DeliverSlip;
use App\Services\Bot\SummaryFormatter;
use App\Services\Bot\Twilio\TwiML;
use Illuminate\Http\Response;

final class FacebookPayloadBuilder
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config) {}

    public function confirmation(AskConfirmation $action): Response
    {
        $text = SummaryFormatter::text($action, true);
        $max = (int) ($this->config['max_summary_chars'] ?? 1900);

        return TwiML::message(mb_strlen($text) > $max ? mb_substr($text, 0, $max - 1).'…' : $text);
    }

    /** PDF attachment + caption; unpaid invoices also carry the hosted /pay link. */
    public function slip(DeliverSlip $action): Response
    {
        $attach = (bool) ($this->config['attach_pdf'] ?? true);

        return TwiML::message(
            $attach ? $action->caption : $action->caption."\n📄 ".$action->pdfUrl,
            $attach ? $action->pdfUrl : null,
        );
    }
}
