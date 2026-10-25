<?php

declare(strict_types=1);

namespace App\Services\Bot\Twilio;

use Illuminate\Http\Response;

/** Minimal TwiML Messaging responses. An empty <Response/> sends nothing (and costs nothing). */
final class TwiML
{
    public static function empty(): Response
    {
        return self::xml('<Response/>');
    }

    public static function message(string $body, ?string $mediaUrl = null): Response
    {
        $xml = '<Response><Message><Body>'.self::escape($body).'</Body>';
        if ($mediaUrl !== null) {
            $xml .= '<Media>'.self::escape($mediaUrl).'</Media>';
        }

        return self::xml($xml.'</Message></Response>');
    }

    private static function xml(string $body): Response
    {
        return new Response('<?xml version="1.0" encoding="UTF-8"?>'.$body, 200, ['Content-Type' => 'text/xml; charset=UTF-8']);
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
