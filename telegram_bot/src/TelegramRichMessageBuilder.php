<?php

declare(strict_types=1);

namespace NetSlip\Bots\Telegram;

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
                self::cell($m($line['unit_minor']), 'right'), self::cell($m($line['total_minor']), 'right'),
            ];
        }
        $totals = [];
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
        $row = [['text' => '🔎 Verify', 'url' => $d->verifyUrl]];
        if ($d->payUrl !== null) {
            $row[] = ['text' => '💳 Pay online', 'url' => $d->payUrl];
        }

        return ['inline_keyboard' => [$row]];
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
