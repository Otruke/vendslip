<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Short, unguessable codes for public links (vendslip.com/s/Ab3dE9xQ2).
 *
 * 9 characters from 56 that can't be confused when read aloud or typed (no 0/O/o, 1/l/I):
 * 56^9 ≈ 5.4 × 10^15 combinations (about 52 bits). With the public rate limit, guessing a valid
 * code is impractical, so the code itself is the key to the slip, like a private share link.
 */
final class ShortCode
{
    public const string ALPHABET = '23456789abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';

    public const int LENGTH = 9;

    public const string PATTERN = '[2-9a-km-zA-HJ-NP-Z]{9}';

    public static function make(): string
    {
        $code = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }

        return $code;
    }

    public static function looksValid(string $code): bool
    {
        return preg_match('/^'.self::PATTERN.'$/', $code) === 1;
    }
}
