<?php

declare(strict_types=1);

namespace App\Support;

final class Urls
{
    public static function frontend(string $path = ''): string
    {
        return rtrim((string) config('vendslip.frontend_url'), '/').'/'.ltrim($path, '/');
    }

    public static function verify(string $hash): string
    {
        return self::frontend('verify/'.$hash);
    }

    /**
     * The short public link for a slip. VENDSLIP_SHARE_URL can point at a shorter domain later
     * (it must serve the web app); otherwise the web app's own address is used.
     */
    public static function slip(string $shortCode): string
    {
        $base = (string) (config('vendslip.share_url') ?: config('vendslip.frontend_url'));

        return rtrim($base, '/').'/s/'.$shortCode;
    }

    public static function pay(string $publicUid): string
    {
        return self::frontend('pay/'.$publicUid);
    }
}
