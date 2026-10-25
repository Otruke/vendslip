<?php

declare(strict_types=1);

namespace App\Services\Payments;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Flutterwave v3 Standard checkout + transaction verification. */
final class FlutterwaveClient
{
    /**
     * @param  array{email: string, name: string, phonenumber?: string|null}  $customer
     * @param  array<string, scalar>  $meta
     */
    public function createPaymentLink(
        GatewayCredentials $credentials,
        string $txRef,
        int $amountMinor,
        string $currency,
        string $redirectUrl,
        array $customer,
        string $title,
        array $meta = [],
    ): string {
        $secret = $credentials->flutterwaveSecretKey ?? throw PaymentGatewayException::notConfigured('Flutterwave');

        try {
            $response = Http::baseUrl((string) config('netslip.flutterwave.base_url'))
                ->withToken($secret)->acceptJson()->timeout(20)->retry(2, 300, throw: false)
                ->post('/payments', [
                    'tx_ref' => $txRef,
                    'amount' => self::major($amountMinor),
                    'currency' => strtoupper($currency),
                    'redirect_url' => $redirectUrl,
                    'payment_options' => 'card,banktransfer,ussd',
                    'customer' => array_filter($customer, static fn ($v): bool => $v !== null && $v !== ''),
                    'customizations' => ['title' => mb_substr($title, 0, 60)],
                    'meta' => $meta,
                ]);
        } catch (ConnectionException $e) {
            throw PaymentGatewayException::failed('Flutterwave', 'Network error.');
        }

        $link = $response->json('data.link');
        if (! $response->successful() || $response->json('status') !== 'success' || ! is_string($link)) {
            throw PaymentGatewayException::failed('Flutterwave', (string) $response->json('message', ''));
        }

        return $link;
    }

    /**
     * Server-side verification (never trust redirect or webhook payloads alone).
     *
     * @return array{status: string, tx_ref: string, amount_minor: int, currency: string, id: string}|null
     */
    public function verifyByReference(GatewayCredentials $credentials, string $txRef): ?array
    {
        $secret = $credentials->flutterwaveSecretKey;
        if ($secret === null) {
            return null;
        }
        try {
            $response = Http::baseUrl((string) config('netslip.flutterwave.base_url'))
                ->withToken($secret)->acceptJson()->timeout(20)
                ->get('/transactions/verify_by_reference', ['tx_ref' => $txRef]);
        } catch (ConnectionException) {
            return null;
        }
        $data = $response->json('data');
        if (! $response->successful() || ! is_array($data)) {
            return null;
        }

        return [
            'status' => (string) ($data['status'] ?? ''),
            'tx_ref' => (string) ($data['tx_ref'] ?? ''),
            'amount_minor' => self::minor($data['amount'] ?? 0),
            'currency' => strtoupper((string) ($data['currency'] ?? '')),
            'id' => (string) ($data['id'] ?? ''),
        ];
    }

    /** Flutterwave sends the dashboard "secret hash" back verbatim in the verif-hash header. */
    public function webhookIsAuthentic(GatewayCredentials $credentials, ?string $header): bool
    {
        $expected = $credentials->flutterwaveSecretHash;

        return $expected !== null && $header !== null && hash_equals($expected, $header);
    }

    private static function major(int $minor): float|int
    {
        return $minor % 100 === 0 ? intdiv($minor, 100) : round($minor / 100, 2);
    }

    private static function minor(mixed $major): int
    {
        return (int) round(((float) $major) * 100);
    }
}
