<?php

declare(strict_types=1);

namespace NetSlip\Bots\WhatsApp;

use App\Services\Bot\Actions\DeliverSlip;
use App\Services\Bot\Twilio\TwiML;
use Illuminate\Http\Response;

final class WhatsAppPayloadBuilder
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config) {}

    /**
     * REST options for a Content API quick-reply message.
     *
     * @return array<string, string>
     */
    public function quickReplyOptions(string $from, string $contentSid, string $contentVariables): array
    {
        return ['from' => $from, 'contentSid' => $contentSid, 'contentVariables' => $contentVariables];
    }

    /** Slip delivery: caption + PDF as WhatsApp document media. */
    public function slip(DeliverSlip $action): Response
    {
        $attach = (bool) ($this->config['attach_pdf'] ?? true);
        $caption = $attach ? $action->caption : $action->caption."\nPDF: ".$action->pdfUrl;

        return TwiML::message($caption, $attach ? $action->pdfUrl : null);
    }
}
