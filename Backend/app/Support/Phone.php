<?php

declare(strict_types=1);

namespace App\Support;

final class Phone
{
    /**
     * International digits for wa.me links / Twilio matching.
     * "0803 998 1122" -> "2348039981122", "+234 803..." -> "234803...", "whatsapp:+234..." -> "234...".
     */
    public static function internationalDigits(?string $phone, string $defaultCountryCode = '234'): ?string
    {
        if ($phone === null) {
            return null;
        }
        $digits = (string) preg_replace('/\D/', '', $phone);
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '00')) {
            return substr($digits, 2);
        }
        if (str_starts_with($digits, '0')) {
            return $defaultCountryCode.substr($digits, 1);
        }

        return $digits;
    }
}
