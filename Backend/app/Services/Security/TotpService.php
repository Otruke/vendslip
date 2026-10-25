<?php

declare(strict_types=1);

namespace App\Services\Security;

use Illuminate\Support\Facades\Cache;

/**
 * RFC 6238 TOTP (SHA-1, 6 digits, 30 s) compatible with Google Authenticator, Authy, 1Password.
 * Accepts ±1 time-step drift and blocks code replay within the acceptance window.
 */
final class TotpService
{
    private const string ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** 20 random bytes = 160-bit secret (RFC 4226 recommendation) → 32 base32 chars. */
    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    public function otpauthUri(string $secret, string $account, string $issuer = 'NetSlip'): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            rawurlencode($issuer),
            rawurlencode($account),
            $secret,
            rawurlencode($issuer),
        );
    }

    public function codeAt(string $secret, int $timeStep): string
    {
        $key = $this->base32Decode($secret);
        $counter = pack('N*', 0, $timeStep);
        $hash = hash_hmac('sha1', $counter, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($binary % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    public function verify(string $secret, string $code, string $replayKey, ?int $now = null): bool
    {
        $code = (string) preg_replace('/\D/', '', $code);
        if (strlen($code) !== 6) {
            return false;
        }
        $step = intdiv($now ?? time(), 30);

        foreach ([-1, 0, 1] as $drift) {
            $candidate = $step + $drift;
            if (hash_equals($this->codeAt($secret, $candidate), $code)) {
                $cacheKey = 'totp-used:'.$replayKey.':'.$candidate;
                if (! Cache::add($cacheKey, true, 120)) {
                    return false; // replay
                }

                return true;
            }
        }

        return false;
    }

    private function base32Encode(string $data): string
    {
        $out = '';
        $buffer = 0;
        $bits = 0;
        foreach (str_split($data) as $char) {
            $buffer = ($buffer << 8) | ord($char);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $out .= self::ALPHABET[($buffer >> $bits) & 0x1F];
            }
            $buffer &= (1 << $bits) - 1;
        }
        if ($bits > 0) {
            $out .= self::ALPHABET[($buffer << (5 - $bits)) & 0x1F];
        }

        return $out;
    }

    private function base32Decode(string $secret): string
    {
        $secret = strtoupper((string) preg_replace('/[^A-Za-z2-7]/', '', $secret));
        $out = '';
        $buffer = 0;
        $bits = 0;
        foreach (str_split($secret) as $char) {
            $value = strpos(self::ALPHABET, $char);
            if ($value === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $value;
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $out .= chr(($buffer >> $bits) & 0xFF);
            }
            $buffer &= (1 << $bits) - 1;
        }

        return $out;
    }
}
