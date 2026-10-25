<?php

declare(strict_types=1);

namespace App\Support;

final class Urls
{
    public static function frontend(string $path = ''): string
    {
        return rtrim((string) config('netslip.frontend_url'), '/').'/'.ltrim($path, '/');
    }

    public static function verify(string $hash): string
    {
        return self::frontend('verify/'.$hash);
    }

    public static function pay(string $publicUid): string
    {
        return self::frontend('pay/'.$publicUid);
    }
}
