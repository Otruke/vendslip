<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use NumberFormatter;

/**
 * Integer minor-unit money helpers. No floats ever touch stored amounts, and no
 * bcmath dependency is required (the Windows PHP build does not ship it enabled).
 */
final class Money
{
    /** Hard ceiling: 10 trillion major units. Keeps every multiplication far below PHP_INT_MAX. */
    public const int MAX_MINOR = 1_000_000_000_000_000;

    private const array SYMBOLS = ['NGN' => '₦', 'USD' => '$', 'GBP' => '£', 'EUR' => '€', 'GHS' => 'GH₵', 'KES' => 'KSh', 'ZAR' => 'R'];

    /**
     * Parse human money input into minor units.
     * Accepts: "15000", "15,000.50", "₦15k", "NGN 1.5m", "N2,500", "$20".
     */
    public static function parseToMinor(string $input): ?int
    {
        $value = mb_strtolower(trim($input));
        if ($value === '') {
            return null;
        }

        $value = str_replace(['₦', '$', '£', '€', 'ngn', 'usd', 'naira', ' ', "\u{00A0}", '_'], '', $value);
        $value = (string) preg_replace('/^n(?=[\d.])/', '', $value); // "N2,500"
        $value = str_replace(',', '', $value);

        if (preg_match('/^(\d*)(?:\.(\d+))?([km])?$/', $value, $m) !== 1 || ($m[1] === '' && ($m[2] ?? '') === '')) {
            return null;
        }

        $intPart = $m[1];
        $fracPart = $m[2] ?? '';
        $suffix = $m[3] ?? '';

        $mantissa = ltrim($intPart.$fracPart, '0');
        if (strlen($mantissa) > 17) {
            return null;
        }
        $mantissa = $mantissa === '' ? 0 : (int) $mantissa;
        $decimals = strlen($fracPart);

        // value = mantissa / 10^decimals * multiplier ; minor = value * 100
        $exponent = 2 + match ($suffix) {
            'k' => 3,
            'm' => 6,
            default => 0,
        } - $decimals;

        if ($exponent >= 0) {
            $minor = $mantissa * (10 ** $exponent);
        } else {
            $divisor = 10 ** (-$exponent);
            $minor = intdiv(2 * $mantissa + $divisor, 2 * $divisor); // round half up
        }

        if ($minor > self::MAX_MINOR) {
            return null;
        }

        return $minor;
    }

    public static function symbol(string $currency): string
    {
        return self::SYMBOLS[strtoupper($currency)] ?? strtoupper($currency).' ';
    }

    /** "₦15,000.00" — deterministic formatting independent of the intl extension. */
    public static function format(int $minor, string $currency = 'NGN', bool $withSymbol = true): string
    {
        $negative = $minor < 0;
        $minor = abs($minor);
        $major = number_format(intdiv($minor, 100)).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);

        return ($negative ? '-' : '').($withSymbol ? self::symbol($currency) : '').$major;
    }

    /** Locale-aware formatting via intl when available (used in admin views). */
    public static function formatIntl(int $minor, string $currency = 'NGN', string $locale = 'en_NG'): string
    {
        if (! class_exists(NumberFormatter::class)) {
            return self::format($minor, $currency);
        }
        $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
        $result = $formatter->formatCurrency($minor / 100, strtoupper($currency));

        return $result === false ? self::format($minor, $currency) : $result;
    }

    /** Round-half-up integer division for non-negative operands. */
    public static function divRound(int $numerator, int $denominator): int
    {
        if ($denominator <= 0 || $numerator < 0) {
            throw new InvalidArgumentException('divRound expects a non-negative numerator and positive denominator.');
        }

        return intdiv(2 * $numerator + $denominator, 2 * $denominator);
    }

    /** Parse a percentage like "7.5", "7.5%", "0" into basis points (750). */
    public static function percentToBps(string $input): ?int
    {
        $value = trim(str_replace('%', '', $input));
        if (preg_match('/^(\d{1,3})(?:\.(\d{1,2}))?$/', $value, $m) !== 1) {
            return null;
        }
        $bps = ((int) $m[1]) * 100 + (int) str_pad($m[2] ?? '', 2, '0');

        return $bps <= 10000 ? $bps : null;
    }
}
