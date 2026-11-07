<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Countries (ISO 3166-1 alpha-2, names from CLDR) bundled in resources/data/countries.json, with a
 * usual currency and a *suggested* standard VAT rate for the countries where that is clear. Tax
 * rates change, so VAT is only ever a prefill the business can edit, never enforced.
 */
final class Countries
{
    /** @var array<string, array{code: string, name: string, currency: string|null, vat: float|null}>|null */
    private static ?array $all = null;

    /** @return array<string, array{code: string, name: string, currency: string|null, vat: float|null}> */
    public static function all(): array
    {
        if (self::$all !== null) {
            return self::$all;
        }
        $raw = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/resources/data/countries.json'), true);
        $list = [];
        foreach (is_array($raw) ? $raw : [] as $c) {
            if (! is_array($c) || ! isset($c['code'], $c['name'])) {
                continue;
            }
            $list[(string) $c['code']] = [
                'code' => (string) $c['code'],
                'name' => (string) $c['name'],
                'currency' => isset($c['currency']) ? (string) $c['currency'] : null,
                'vat' => isset($c['vat']) ? (float) $c['vat'] : null,
            ];
        }

        return self::$all = $list;
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::all());
    }

    public static function name(?string $code): ?string
    {
        return $code !== null ? (self::all()[strtoupper($code)]['name'] ?? null) : null;
    }
}
