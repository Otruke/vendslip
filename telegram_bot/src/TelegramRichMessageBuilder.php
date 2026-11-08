<?php

declare(strict_types=1);

namespace VendSlip\Bots\Telegram;

use App\Services\Bot\Actions\AskConfirmation;
use App\Services\Bot\Actions\DeliverSlip;
use App\Support\Money;

/**
 * Builds Bot API 10.x InputRichMessage payloads:
 *  - InputRichBlockParagraph       {type: "paragraph", text}
 *  - InputRichBlockTable           {type: "table", cells: RichBlockTableCell[][], is_compact, is_bordered}
 *  - InputRichBlockExpandableBlockQuotation {type: "expandable_blockquote", text}
 *  - InputRichBlockDocument        {type: "document", document: InputMediaDocument}
 * RichText values are plain strings (allowed by the API).
 */
final class TelegramRichMessageBuilder
{
    public function __construct(private readonly string $callbackPrefix = 'ns:') {}

    /** @return array<string, mixed> InputRichMessage */
    public function summary(AskConfirmation $a): array
    {
        $m = static fn (int $minor): string => Money::format($minor, $a->currency);

        $rows = [[
            self::cell('Item', 'left', true), self::cell('Qty', 'right', true),
            self::cell('Unit', 'right', true), self::cell('Amount', 'right', true),
        ]];
        foreach ($a->lines as $line) {
            $rows[] = [
                self::cell($line['description'], 'left'), self::cell((string) $line['quantity'], 'right'),
                self::cell($m($line['unit_minor']).(isset($line['list_minor']) ? ' (was '.$m($line['list_minor']).')' : ''), 'right'),
                self::cell($m($line['total_minor']), 'right'),
            ];
        }
        $totals = [];
        if ($a->discountMinor > 0 || $a->taxMinor > 0 || $a->shippingMinor > 0 || $a->lineDiscountMinor > 0) {
            $totals[] = ['Subtotal', $m($a->subtotalMinor)];
        }
        if ($a->lineDiscountMinor > 0) {
            $totals[] = ['Item discounts (in subtotal)', '-'.$m($a->lineDiscountMinor)];
        }
        if ($a->discountMinor > 0) {
            $totals[] = ['Discount', '-'.$m($a->discountMinor)];
        }
        if ($a->taxMinor > 0) {
            $totals[] = [$a->taxLabel, $m($a->taxMinor)];
        }
        if ($a->shippingMinor > 0) {
            $totals[] = ['Shipping', $m($a->shippingMinor)];
        }
        $totals[] = ['Total', $m($a->totalMinor)];
        if ($a->balanceMinor > 0) {
            $totals[] = ['Paid', $m($a->paidMinor)];
            $totals[] = ['Balance due', $m($a->balanceMinor)];
        }
        foreach ($totals as [$label, $value]) {
            $rows[] = [
                ['align' => 'left', 'valign' => 'middle', 'text' => $label, 'is_header' => $label === 'Total', 'colspan' => 3],
                self::cell($value, 'right', $label === 'Total'),
            ];
        }

        $who = trim($a->customerName.($a->customerPhone !== null ? ' · '.$a->customerPhone : ''));
        $blocks = [
            ['type' => 'paragraph', 'text' => ($a->isUpdate ? 'Updated ' : '').'🧾 '.$a->docLabel.' for '.$who],
            ['type' => 'table', 'cells' => $rows, 'is_compact' => true, 'is_bordered' => true],
        ];
        if ($a->customerAddress !== null) {
            $blocks[] = ['type' => 'expandable_blockquote', 'text' => 'Deliver to: '.$a->customerAddress];
        }
        if ($a->willWatermark) {
            $blocks[] = ['type' => 'paragraph', 'text' => $a->canUseCleanCredit
                ? 'Free slip (watermarked) — or tap "Clean slip" to spend 1 credit.'
                : 'Free slip (watermarked).'];
        }

        return ['blocks' => $blocks];
    }

    /** @return array<string, mixed> InputRichMessage */
    public function delivery(DeliverSlip $d): array
    {
        $blocks = [
            ['type' => 'paragraph', 'text' => $d->caption],
            ['type' => 'document', 'document' => ['type' => 'document', 'media' => $d->pdfUrl]],
            ['type' => 'expandable_blockquote', 'text' => 'Proof: '.$d->document->verification_code."\nHMAC-SHA256 ".$d->document->verification_hash],
        ];

        return ['blocks' => $blocks];
    }

    /** @return array<string, mixed> InputRichMessage */
    public function renderingDraft(): array
    {
        return ['markdown' => '⏳ *Rendering your slip…*'];
    }

    /** @return array<string, mixed> InlineKeyboardMarkup */
    public function confirmKeyboard(AskConfirmation $a, bool $disabled = false): array
    {
        $first = [
            $this->button('✅ Confirm & Generate', 'confirm', $disabled),
        ];
        if ($a->canUseCleanCredit) {
            $first[] = $this->button('✨ Clean slip (1 credit)', 'clean', $disabled);
        }

        return ['inline_keyboard' => [
            $first,
            [$this->button('✏️ Edit', 'edit', $disabled), $this->button('❌ Cancel', 'cancel', $disabled)],
        ]];
    }

    /**
     * Bot API 10.3 DisabledButton: buttons stay visible but can't be tapped again.
     *
     * @return array<string, mixed>
     */
    public function disabledKeyboard(string $label = '⏳ Generating…'): array
    {
        return ['inline_keyboard' => [[['text' => $label, 'callback_data' => $this->callbackPrefix.'noop', 'disabled' => true]]]];
    }

    /** @return array<string, mixed> */
    public function verifyKeyboard(DeliverSlip $d): array
    {
        $url = $d->shareUrl !== '' ? $d->shareUrl : $d->verifyUrl;

        return ['inline_keyboard' => [[['text' => $d->payUrl !== null ? '💳 View & pay' : '📄 View & download', 'url' => $url]]]];
    }

    /**
     * Telegram refuses a whole message when a button points at an address it can't use (localhost,
     * a private IP, plain http to a bare host). Then the slip is sent without the button: the link is
     * in the text anyway.
     */
    public static function publicUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (! preg_match('#^https?://#i', $url) || $host === '' || ! str_contains($host, '.')) {
            return false;
        }
        if (in_array($host, ['127.0.0.1', '0.0.0.0'], true) || str_ends_with($host, '.local') || str_ends_with($host, '.localhost') || str_ends_with($host, '.test')) {
            return false;
        }

        return filter_var($host, FILTER_VALIDATE_IP) === false
            || filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /** @return array<string, mixed> */
    private function button(string $text, string $action, bool $disabled): array
    {
        $button = ['text' => $text, 'callback_data' => $this->callbackPrefix.$action];
        if ($disabled) {
            $button['disabled'] = true;
        }

        return $button;
    }

    /** @return array<string, mixed> RichBlockTableCell */
    private static function cell(string $text, string $align, bool $header = false): array
    {
        $cell = ['align' => $align, 'valign' => 'middle', 'text' => $text];
        if ($header) {
            $cell['is_header'] = true;
        }

        return $cell;
    }
}
