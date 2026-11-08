<?php

declare(strict_types=1);

namespace App\Services\Slash;

use App\Support\Money;

/**
 * Parses vendor chat messages written with VendSlip /slash syntax.
 *
 *   /name = Chief Emeka, /amount = 15000
 *   /item = 2x Leather Sneakers @ 28,500 | 1x Belt @ 5k
 *   /type = invoice
 *
 * Keys may arrive one per message or all in one message (newline, comma or
 * semicolon separated). Bare commands (/confirm, /cancel, /help, /link CODE ...)
 * are only recognised when they are the whole message.
 *
 * Mirrored 1:1 in Frontend/src/lib/slash.ts — keep both in sync.
 */
final class SlashCommandParser
{
    /** alias => canonical key */
    public const array KEY_ALIASES = [
        'name' => 'name', 'customer' => 'name', 'client' => 'name', 'buyer' => 'name',
        'phone' => 'phone', 'tel' => 'phone', 'mobile' => 'phone', 'whatsapp' => 'phone',
        'email' => 'email', 'mail' => 'email',
        'address' => 'address', 'addr' => 'address', 'location' => 'address',
        'amount' => 'amount', 'total' => 'amount', 'price' => 'amount',
        'item' => 'item', 'items' => 'item', 'product' => 'item',
        'type' => 'type', 'doc' => 'type',
        'discount' => 'discount', 'disc' => 'discount',
        'shipping' => 'shipping', 'delivery' => 'shipping', 'logistics' => 'shipping',
        'paid' => 'paid', 'deposit' => 'paid',
        'tax' => 'vat', 'vat' => 'vat',
        'due' => 'due', 'duedate' => 'due', 'due_date' => 'due',
        'note' => 'note', 'notes' => 'note', 'memo' => 'note',
        'template' => 'template',
        'currency' => 'currency', 'cur' => 'currency', 'ccy' => 'currency',
    ];

    /** alias => canonical command */
    public const array COMMANDS = [
        'confirm' => 'confirm', 'yes' => 'confirm', 'ok' => 'confirm', 'generate' => 'confirm', 'send' => 'confirm',
        'cancel' => 'cancel', 'stop' => 'cancel', 'discard' => 'cancel',
        'edit' => 'edit',
        'clear' => 'clear', 'reset' => 'clear', 'new' => 'clear',
        'remove' => 'remove', 'delete' => 'remove',
        'preview' => 'preview', 'summary' => 'preview', 'status' => 'preview',
        'help' => 'help', 'start' => 'help', 'commands' => 'help',
        'link' => 'link',
        'unlink' => 'unlink',
        'credits' => 'credits', 'balance' => 'credits',
    ];

    private const string KEY_PATTERN = '/(?:^|(?<=[\s,;]))\/([A-Za-z_]+)\s*=/u';

    public function parse(string $message): ParseResult
    {
        $result = new ParseResult;
        $text = trim(str_replace(["\r\n", "\r"], "\n", $message));
        if ($text === '') {
            return $result;
        }

        // Bare command: the whole message is "/cmd" or "/cmd argument" (no "=").
        if (preg_match('/^\/([A-Za-z_]+)(?:@\w+)?(?:\s+([^=]*))?$/u', $text, $m) === 1) {
            $alias = strtolower($m[1]);
            if (isset(self::COMMANDS[$alias])) {
                $result->command = self::COMMANDS[$alias];
                $argument = trim($m[2] ?? '');
                $result->commandArgument = $argument === '' ? null : $argument;

                return $result;
            }
        }

        if (preg_match_all(self::KEY_PATTERN, $text, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return $result;
        }

        $count = count($matches[0]);
        for ($i = 0; $i < $count; $i++) {
            $alias = strtolower($matches[1][$i][0]);
            $valueStart = $matches[0][$i][1] + strlen($matches[0][$i][0]);
            $valueEnd = $i + 1 < $count ? $matches[0][$i + 1][1] : strlen($text);
            $value = trim(substr($text, $valueStart, $valueEnd - $valueStart));
            $value = trim(rtrim($value, ",; \t\n"));

            $key = self::KEY_ALIASES[$alias] ?? null;
            if ($key === null) {
                $result->unknownKeys[] = $alias;

                continue;
            }
            if ($value === '') {
                continue;
            }

            if ($key === 'item') {
                foreach ($this->splitItems($value) as $line) {
                    $item = $this->readItem($line);
                    if (is_string($item)) {
                        $result->errors[] = $item;
                    } else {
                        $result->items[] = $item;
                    }
                }

                continue;
            }

            $result->fields[$key] = $value;
        }

        return $result;
    }

    /** @return list<string> */
    private function splitItems(string $value): array
    {
        $parts = preg_split('/\s*[|\n;]\s*/u', $value) ?: [];

        return array_values(array_filter(array_map('trim', $parts), static fn (string $p): bool => $p !== ''));
    }

    /**
     * Supported shapes:
     *   "2x Shoe @ 15000", "2 x Shoe @ 15,000", "Shoe x2 @ 15k", "Shoe @ 15000 x 2",
     *   "3 Shoes @ 2000", "Shoe - 15000", "Shoe 15000", "2x Shoe" (price resolved from inventory later)
     *   "2x Shoe @ 15000 was 18000" (sold at 15,000, normally 18,000: the line discount is recorded)
     */
    public function parseItem(string $line): ?ParsedItem
    {
        $item = $this->readItem($line);

        return $item instanceof ParsedItem ? $item : null;
    }

    /**
     * Like parseItem(), but says what is wrong. Also reads a discount on the item:
     *   "2x Shoe @ 15000 less 1000"         ₦1,000 off each one (sold at 14,000 each)
     *   "2x Shoe @ 15000 less 10%"          10% off each one (also "-10%", "off 10%", "disc 10%")
     *   "2x Shoe @ 15000 less 3000 total"   ₦3,000 off the two together ("all", "for all", "in total")
     * The price before the discount is kept as the normal price, so the slip shows the saving.
     */
    public function readItem(string $line): ParsedItem|string
    {
        $raw = trim($line);
        $usage = "Couldn't read item \"{$raw}\". Try: 2x Shoe @ 15000";
        if ($raw === '') {
            return $usage;
        }
        $work = $raw;

        // A discount on this item: "less 1000", "off 10%", "disc 500 each", "less 3000 total", "-10%".
        $discount = null;
        $amount = '(?:₦|NGN|N|\$)?\s*\d[\d,]*(?:\.\d+)?\s*[kKmM]?';
        if (preg_match('/^(.*\S)\s+(?:less|off|disc|discount)\s*[:=]?\s*(\d{1,3}(?:\.\d{1,2})?\s*%|'.$amount.')\s*(each|per\s+(?:unit|item|one|piece)|total|all|for\s+all|on\s+all|in\s+total|line)?\s*$/iu', $work, $dm) === 1) {
            $discount = ['value' => trim($dm[2]), 'whole' => in_array(preg_replace('/\s+/', ' ', strtolower($dm[3] ?? '')), ['total', 'all', 'for all', 'on all', 'in total', 'line'], true)];
            $work = trim($dm[1]);
        } elseif (preg_match('/^(.*\S)\s+(\d{1,3}(?:\.\d{1,2})?\s*%|'.$amount.')\s*off\s*(each|per\s+(?:unit|item|one|piece)|total|all|for\s+all|on\s+all|in\s+total|line)?\s*$/iu', $work, $dm) === 1 && str_contains($dm[1], '@')) {
            // "@ 15000 10% off", "@ 15000 1000 off each", "@ 15000 3000 off total"
            $discount = ['value' => trim($dm[2]), 'whole' => in_array(preg_replace('/\s+/', ' ', strtolower($dm[3] ?? '')), ['total', 'all', 'for all', 'on all', 'in total', 'line'], true)];
            $work = trim($dm[1]);
        } elseif (preg_match('/^(.*\S)\s+-\s*(\d{1,3}(?:\.\d{1,2})?)\s*%\s*$/u', $work, $dm) === 1) {
            $discount = ['value' => $dm[2].'%', 'whole' => false];
            $work = trim($dm[1]);
        }

        // "… @ 15000 was 18000": the normal (list) price, so the line's discount is traceable.
        $listPrice = null;
        if (preg_match('/^(.*\S)\s*\(?\s*(?:was|list|rrp|normally)\s*[:=]?\s*((?:₦|NGN|N|\$)?\s*\d[\d,]*(?:\.\d+)?\s*[kKmM]?)\s*\)?\s*$/iu', $work, $lm) === 1) {
            if ($discount !== null) {
                return "Item \"{$raw}\": use either \"was\" (the normal price) or \"less\" (the discount), not both.";
            }
            $listPrice = Money::parseToMinor(trim($lm[2]));
            if ($listPrice === null) {
                return $usage;
            }
            $work = trim($lm[1]);
        }

        $description = $work;
        $priceText = null;
        $quantity = null;

        if (str_contains($work, '@')) {
            $at = (int) strrpos($work, '@');
            $description = trim(substr($work, 0, $at));
            $priceText = trim(substr($work, $at + 1));
            // "Shoe @ 15000 x 2"
            if (preg_match('/^(.+?)\s*[x×*]\s*(\d{1,6})$/iu', $priceText, $pm) === 1) {
                $priceText = trim($pm[1]);
                $quantity = (int) $pm[2];
            }
        } elseif (preg_match('/^(.*?)(?:\s+-\s+|\s+)((?:₦|NGN|N|\$)?\s*\d[\d,]*(?:\.\d+)?\s*[kKmM]?)$/u', $work, $pm) === 1 && trim($pm[1]) !== '') {
            $description = trim($pm[1]);
            $priceText = trim($pm[2]);
        }

        // Quantity in the description
        if ($quantity === null) {
            if (preg_match('/^(\d{1,6})\s*[x×*]\s*(.+)$/iu', $description, $qm) === 1) {
                $quantity = (int) $qm[1];
                $description = trim($qm[2]);
            } elseif (preg_match('/^(.+?)\s*[x×*]\s*(\d{1,6})$/iu', $description, $qm) === 1) {
                $quantity = (int) $qm[2];
                $description = trim($qm[1]);
            } elseif (preg_match('/^(\d{1,6})\s+(\D.*)$/u', $description, $qm) === 1) {
                $quantity = (int) $qm[1];
                $description = trim($qm[2]);
            }
        }

        $description = trim($description, " \t-:");
        if ($description === '' || mb_strlen($description) > 500) {
            return $usage;
        }

        $unitPrice = null;
        if ($priceText !== null) {
            $unitPrice = Money::parseToMinor($priceText);
            if ($unitPrice === null) {
                return $usage;
            }
        }

        $quantity ??= 1;
        if ($quantity < 1) {
            return $usage;
        }

        if ($discount !== null) {
            if ($unitPrice === null) {
                return "Item \"{$raw}\": add the price before the discount, e.g. 2x Shoe @ 15000 less 1000.";
            }
            if (str_ends_with($discount['value'], '%')) {
                $bps = Money::percentToBps($discount['value']);
                if ($bps === null || $bps === 0 || $bps >= 10000) {
                    return "Item \"{$raw}\": the discount must be more than 0% and less than 100%.";
                }
                // A percentage is the same on each one or on the whole line.
                $perUnit = intdiv($unitPrice * $bps + 5000, 10000);
            } else {
                $off = Money::parseToMinor($discount['value']);
                if ($off === null || $off === 0) {
                    return $usage;
                }
                if ($discount['whole'] && $quantity > 1) {
                    if ($off % $quantity !== 0) {
                        return sprintf(
                            'Item "%s": %s off %d items doesn\'t split evenly. Give the discount on each one (e.g. less %s each), or use /discount = %s for the whole order.',
                            $raw, self::plain($off), $quantity, self::plain(intdiv($off, $quantity)), self::plain($off),
                        );
                    }
                    $perUnit = intdiv($off, $quantity);
                } else {
                    $perUnit = $off;
                }
            }
            if ($perUnit >= $unitPrice) {
                return "Item \"{$raw}\": the discount is as much as the price or more.";
            }
            $listPrice = $unitPrice;
            $unitPrice -= $perUnit;
        }

        return new ParsedItem($description, $quantity, $unitPrice, $raw, $listPrice);
    }

    /** 150000 → "1500", 150050 → "1500.50": an amount as people type it, without a currency sign. */
    private static function plain(int $minor): string
    {
        return $minor % 100 === 0 ? (string) intdiv($minor, 100) : number_format($minor / 100, 2, '.', '');
    }
}
