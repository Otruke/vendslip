<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Every active ISO 4217 currency (resources/data/currencies.json, generated from the Unicode CLDR
 * data that ships with every browser and Node). Bundled rather than fetched, so the list works
 * offline, on any PHP build (with or without intl) and never depends on a third-party API.
 *
 * Amounts are always stored in hundredths ("minor" = major × 100) whatever the currency, so the
 * maths is identical everywhere; currencies without decimals (UGX, XAF, RWF, JPY…) simply display
 * as whole numbers.
 */
final class Currencies
{
    /** Friendlier local symbols where CLDR's narrow symbol is just the code. */
    private const array SYMBOL_OVERRIDES = [
        'KES' => 'KSh', 'UGX' => 'USh', 'TZS' => 'TSh', 'ZAR' => 'R', 'CDF' => 'FC', 'ZMW' => 'K', 'MWK' => 'MK',
        'ETB' => 'Br', 'EGP' => 'E£', 'MAD' => 'DH', 'BWP' => 'P', 'SLE' => 'Le', 'GMD' => 'D', 'LRD' => 'L$',
        'NAD' => 'N$', 'AED' => 'AED ', 'SAR' => 'SAR ', 'KWD' => 'KD ', 'QAR' => 'QR ',
    ];

    /** @var array<string, array{code: string, name: string, symbol: string, digits: int}>|null */
    private static ?array $all = null;

    /** @return array<string, array{code: string, name: string, symbol: string, digits: int}> keyed by code */
    public static function all(): array
    {
        if (self::$all !== null) {
            return self::$all;
        }
        $raw = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/resources/data/currencies.json'), true);
        $list = [];
        foreach (is_array($raw) ? $raw : [] as $c) {
            if (! is_array($c) || ! isset($c['code'], $c['name'])) {
                continue;
            }
            $code = (string) $c['code'];
            $list[$code] = [
                'code' => $code,
                'name' => (string) $c['name'],
                'symbol' => self::SYMBOL_OVERRIDES[$code] ?? (string) ($c['symbol'] ?? $code),
                'digits' => (int) ($c['digits'] ?? 2) === 0 ? 0 : 2,
            ];
        }
        ksort($list);

        return self::$all = $list;
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::all());
    }

    public static function exists(?string $code): bool
    {
        return $code !== null && isset(self::all()[strtoupper($code)]);
    }

    public static function symbol(string $code): string
    {
        $code = strtoupper($code);
        $symbol = self::all()[$code]['symbol'] ?? $code.' ';

        // A bare code reads better with a space: "UGX 50,000".
        return preg_match('/^[A-Z]{3}$/', $symbol) === 1 ? $symbol.' ' : $symbol;
    }

    public static function digits(string $code): int
    {
        return self::all()[strtoupper($code)]['digits'] ?? 2;
    }

    public static function name(string $code): string
    {
        return self::all()[strtoupper($code)]['name'] ?? strtoupper($code);
    }
}
