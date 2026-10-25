<?php

declare(strict_types=1);

namespace App\Services\Slash;

use App\Support\Money;

/**
 * Parses vendor chat messages written with NetSlip /slash syntax.
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
                    $item = $this->parseItem($line);
                    if ($item === null) {
                        $result->errors[] = "Couldn't read item \"{$line}\". Try: 2x Shoe @ 15000";
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
     */
    public function parseItem(string $line): ?ParsedItem
    {
        $raw = trim($line);
        if ($raw === '') {
            return null;
        }

        $description = $raw;
        $priceText = null;
        $quantity = null;

        if (str_contains($raw, '@')) {
            $at = (int) strrpos($raw, '@');
            $description = trim(substr($raw, 0, $at));
            $priceText = trim(substr($raw, $at + 1));
            // "Shoe @ 15000 x 2"
            if (preg_match('/^(.+?)\s*[x×*]\s*(\d{1,6})$/iu', $priceText, $pm) === 1) {
                $priceText = trim($pm[1]);
                $quantity = (int) $pm[2];
            }
        } elseif (preg_match('/^(.*?)(?:\s+-\s+|\s+)((?:₦|NGN|N|\$)?\s*\d[\d,]*(?:\.\d+)?\s*[kKmM]?)$/u', $raw, $pm) === 1 && trim($pm[1]) !== '') {
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
            return null;
        }

        $unitPrice = null;
        if ($priceText !== null) {
            $unitPrice = Money::parseToMinor($priceText);
            if ($unitPrice === null) {
                return null;
            }
        }

        $quantity ??= 1;
        if ($quantity < 1) {
            return null;
        }

        return new ParsedItem($description, $quantity, $unitPrice, $raw);
    }
}
