<?php

declare(strict_types=1);

namespace App\Services\Slash;

use App\Data\DocumentDraftData;
use App\Data\DocumentItemData;
use App\Enums\DocumentType;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * The accumulating state of a /slash draft (stored in bot_draft_sessions.draft_payload_json
 * and mirrored by the React composer store).
 *
 * @phpstan-type ItemShape array{description: string, quantity: int, unit_price_minor: int|null, sku_id?: string|null}
 * @phpstan-type PayloadShape array{
 *   type: string|null, name: string|null, phone: string|null, email: string|null, address: string|null,
 *   items: list<ItemShape>, amount_minor: int|null, discount_minor: int|null, discount_bps: int|null,
 *   shipping_minor: int|null, paid: int|string|null, vat_rate_bps: int|null, vat_inclusive: bool|null,
 *   due_date: string|null, note: string|null, template: string|null
 * }
 */
final class DraftPayload
{
    public const string PAID_FULL = 'FULL';

    /** @param PayloadShape $data */
    private function __construct(private array $data) {}

    public static function empty(): self
    {
        return new self([
            'type' => null, 'name' => null, 'phone' => null, 'email' => null, 'address' => null,
            'items' => [], 'amount_minor' => null, 'discount_minor' => null, 'discount_bps' => null,
            'shipping_minor' => null, 'paid' => null, 'vat_rate_bps' => null, 'vat_inclusive' => null,
            'due_date' => null, 'note' => null, 'template' => null,
        ]);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $payload = self::empty();
        foreach ($payload->data as $key => $_) {
            if (array_key_exists($key, $data)) {
                $payload->data[$key] = $data[$key];
            }
        }
        if (! is_array($payload->data['items'])) {
            $payload->data['items'] = [];
        }

        return $payload;
    }

    /** @return PayloadShape */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Merge a parsed message into the draft. Scalar keys overwrite, /item appends.
     *
     * @return array{0: self, 1: list<string>} new payload and validation errors
     */
    public function apply(ParseResult $result, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::now(config('app.timezone'));
        $next = clone $this;
        $errors = $result->errors;

        foreach ($result->fields as $key => $value) {
            $error = $next->applyField($key, $value, $today);
            if ($error !== null) {
                $errors[] = $error;
            }
        }

        foreach ($result->items as $item) {
            $next->data['items'][] = $item->toArray();
        }

        foreach ($result->unknownKeys as $unknown) {
            $errors[] = "/{$unknown} isn't a NetSlip field. Send /help for the list.";
        }

        return [$next, $errors];
    }

    private function applyField(string $key, string $value, CarbonImmutable $today): ?string
    {
        $lower = mb_strtolower(trim($value));

        switch ($key) {
            case 'type':
                $type = match (true) {
                    in_array($lower, ['receipt', 'rcp', 'r', 'rct'], true) => DocumentType::RECEIPT->value,
                    in_array($lower, ['invoice', 'inv', 'i', 'bill'], true) => DocumentType::INVOICE->value,
                    default => null,
                };
                if ($type === null) {
                    return '/type must be receipt or invoice.';
                }
                $this->data['type'] = $type;

                return null;

            case 'name':
                $this->data['name'] = mb_substr($value, 0, 160);

                return null;

            case 'address':
                $this->data['address'] = mb_substr($value, 0, 500);

                return null;

            case 'note':
                $this->data['note'] = mb_substr($value, 0, 1000);

                return null;

            case 'template':
                $this->data['template'] = mb_substr($lower, 0, 100);

                return null;

            case 'phone':
                $phone = (string) preg_replace('/[^\d+]/', '', $value);
                $digits = strlen(ltrim($phone, '+'));
                if ($digits < 7 || $digits > 15) {
                    return "That phone number doesn't look right: {$value}";
                }
                $this->data['phone'] = $phone;

                return null;

            case 'email':
                if (filter_var(trim($value), FILTER_VALIDATE_EMAIL) === false) {
                    return "That email doesn't look right: {$value}";
                }
                $this->data['email'] = mb_strtolower(trim($value));

                return null;

            case 'amount':
            case 'shipping':
                $minor = Money::parseToMinor($value);
                if ($minor === null) {
                    return "Couldn't read /{$key} \"{$value}\". Try 15000 or 15k.";
                }
                $this->data[$key === 'amount' ? 'amount_minor' : 'shipping_minor'] = $minor;

                return null;

            case 'discount':
                if (str_ends_with($lower, '%')) {
                    $bps = Money::percentToBps($lower);
                    if ($bps === null) {
                        return "Couldn't read /discount \"{$value}\".";
                    }
                    $this->data['discount_bps'] = $bps;
                    $this->data['discount_minor'] = null;

                    return null;
                }
                $minor = Money::parseToMinor($value);
                if ($minor === null) {
                    return "Couldn't read /discount \"{$value}\".";
                }
                $this->data['discount_minor'] = $minor;
                $this->data['discount_bps'] = null;

                return null;

            case 'paid':
                if (in_array($lower, ['full', 'all', 'yes', 'paid'], true)) {
                    $this->data['paid'] = self::PAID_FULL;

                    return null;
                }
                if (in_array($lower, ['none', 'no', 'nothing'], true)) {
                    $this->data['paid'] = 0;

                    return null;
                }
                $minor = Money::parseToMinor($value);
                if ($minor === null) {
                    return "Couldn't read /paid \"{$value}\". Use an amount or 'full'.";
                }
                $this->data['paid'] = $minor;

                return null;

            case 'vat':
                return $this->applyVat($lower);

            case 'due':
                $date = self::parseDueDate($lower, $today);
                if ($date === null) {
                    return "Couldn't read /due \"{$value}\". Try 7d or 2026-11-30.";
                }
                $this->data['due_date'] = $date->toDateString();

                return null;
        }

        return null;
    }

    private function applyVat(string $value): ?string
    {
        $inclusive = null;
        if (preg_match('/\b(incl|inclusive|inc)\b/', $value) === 1) {
            $inclusive = true;
        } elseif (preg_match('/\b(excl|exclusive|exc)\b/', $value) === 1) {
            $inclusive = false;
        }
        $rate = trim((string) preg_replace('/\b(incl|inclusive|inc|excl|exclusive|exc)\b/', '', $value));

        if (in_array($rate, ['off', 'no', 'none', '0', '0%'], true)) {
            $this->data['vat_rate_bps'] = 0;
        } elseif (in_array($rate, ['', 'on', 'yes', 'default'], true)) {
            $this->data['vat_rate_bps'] = null; // merchant default
        } else {
            $bps = Money::percentToBps($rate);
            if ($bps === null) {
                return "Couldn't read /vat \"{$value}\". Try 7.5 or off.";
            }
            $this->data['vat_rate_bps'] = $bps;
        }
        if ($inclusive !== null) {
            $this->data['vat_inclusive'] = $inclusive;
        }

        return null;
    }

    public static function parseDueDate(string $value, CarbonImmutable $today): ?CarbonImmutable
    {
        $value = trim($value);
        if (preg_match('/^\+?(\d{1,3})\s*(d|day|days)?$/', $value, $m) === 1) {
            return $today->startOfDay()->addDays((int) $m[1]);
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) === 1 && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return CarbonImmutable::create((int) $m[1], (int) $m[2], (int) $m[3], 0, 0, 0, $today->getTimezone());
        }
        // Day-first (Nigeria/UK): 12/11/2026, 12-11-2026, 12.11.2026
        if (preg_match('/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{4})$/', $value, $m) === 1 && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return CarbonImmutable::create((int) $m[3], (int) $m[2], (int) $m[1], 0, 0, 0, $today->getTimezone());
        }
        if ($value === 'tomorrow') {
            return $today->startOfDay()->addDay();
        }

        return null;
    }

    public function removeItem(int $oneBasedIndex): bool
    {
        $index = $oneBasedIndex - 1;
        if (! isset($this->data['items'][$index])) {
            return false;
        }
        array_splice($this->data['items'], $index, 1);

        return true;
    }

    /** @param list<ItemShape> $items */
    public function withItems(array $items): self
    {
        $next = clone $this;
        $next->data['items'] = $items;

        return $next;
    }

    public function type(): DocumentType
    {
        return DocumentType::tryFrom((string) $this->data['type']) ?? DocumentType::RECEIPT;
    }

    public function name(): ?string
    {
        return $this->data['name'];
    }

    /** @return list<ItemShape> */
    public function items(): array
    {
        return $this->data['items'];
    }

    public function template(): ?string
    {
        return $this->data['template'];
    }

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    /** @return list<string> human labels of what is still required */
    public function missing(): array
    {
        $missing = [];
        if (blank($this->data['name'])) {
            $missing[] = '/name';
        }
        if ($this->data['items'] === [] && $this->data['amount_minor'] === null) {
            $missing[] = '/amount or /item';
        }
        foreach ($this->data['items'] as $i => $item) {
            if ($item['unit_price_minor'] === null) {
                $missing[] = 'price for item '.($i + 1).' ('.$item['description'].')';
            }
        }

        return $missing;
    }

    public function isReady(): bool
    {
        return $this->missing() === [];
    }

    public function isBlank(): bool
    {
        return $this->data === self::empty()->data;
    }

    /**
     * Normalised line items: a lump-sum /amount becomes a single line.
     *
     * @return list<array{description: string, quantity: int, unit_price_minor: int, sku_id: string|null}>
     */
    public function resolvedLines(): array
    {
        $lines = [];
        foreach ($this->data['items'] as $item) {
            $lines[] = [
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price_minor' => (int) $item['unit_price_minor'],
                'sku_id' => $item['sku_id'] ?? null,
            ];
        }
        if ($lines === [] && $this->data['amount_minor'] !== null) {
            $lines[] = [
                'description' => 'Goods & services',
                'quantity' => 1,
                'unit_price_minor' => $this->data['amount_minor'],
                'sku_id' => null,
            ];
        }

        return $lines;
    }

    /** Discount in minor units for a given subtotal (percent discounts are resolved here). */
    public function discountFor(int $subtotalMinor): int
    {
        $bps = $this->data['discount_bps'];
        if (is_int($bps)) {
            return intdiv($subtotalMinor * $bps + 5000, 10000);
        }

        return (int) ($this->data['discount_minor'] ?? 0);
    }

    /** Convert a ready draft into the issuance DTO. Payment defaults follow the document type. */
    public function toDraftData(?int $templateId = null, bool $useCredit = false): DocumentDraftData
    {
        $lines = $this->resolvedLines();
        $subtotal = array_sum(array_map(static fn (array $l): int => $l['quantity'] * $l['unit_price_minor'], $lines));
        $paid = $this->data['paid'];

        return new DocumentDraftData(
            type: $this->type(),
            customer_name: (string) $this->data['name'],
            items: array_map(static fn (array $l): DocumentItemData => new DocumentItemData(
                description: $l['description'],
                quantity: $l['quantity'],
                unit_price_minor: $l['unit_price_minor'],
                sku_id: $l['sku_id'],
            ), $lines),
            customer_phone: $this->data['phone'],
            customer_email: $this->data['email'],
            customer_address: $this->data['address'],
            discount_minor: $this->discountFor($subtotal),
            shipping_minor: (int) ($this->data['shipping_minor'] ?? 0),
            vat_rate_bps: $this->data['vat_rate_bps'],
            vat_inclusive: $this->data['vat_inclusive'],
            amount_paid_minor: $paid === self::PAID_FULL || $paid === null ? null : (int) $paid,
            due_date: $this->type() === DocumentType::INVOICE ? $this->data['due_date'] : null,
            notes: $this->data['note'],
            template_id: $templateId,
            use_paid_credit: $useCredit,
        );
    }
}
