<?php

declare(strict_types=1);

namespace App\Services\Payments;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** NOWPayments hosted crypto invoices (USDT TRC-20/BEP-20, USDC, BTC, TON ...). */
final class NowPaymentsClient
{
    /** @return array{id: string, invoice_url: string} */
    public function createInvoice(
        GatewayCredentials $credentials,
        string $orderId,
        int $amountMinor,
        string $currency,
        string $description,
        string $ipnUrl,
        string $successUrl,
        string $cancelUrl,
    ): array {
        $key = $credentials->nowPaymentsApiKey ?? throw PaymentGatewayException::notConfigured('Crypto payments');

        try {
            $response = Http::baseUrl((string) config('netslip.nowpayments.base_url'))
                ->withHeaders(['x-api-key' => $key])->acceptJson()->timeout(20)
                ->post('/invoice', [
                    'price_amount' => round($amountMinor / 100, 2),
                    'price_currency' => strtolower($currency),
                    'order_id' => $orderId,
                    'order_description' => mb_substr($description, 0, 120),
                    'ipn_callback_url' => $ipnUrl,
                    'success_url' => $successUrl,
                    'cancel_url' => $cancelUrl,
                ]);
        } catch (ConnectionException) {
            throw PaymentGatewayException::failed('NOWPayments', 'Network error.');
        }

        $url = $response->json('invoice_url');
        if (! $response->successful() || ! is_string($url)) {
            throw PaymentGatewayException::failed('NOWPayments', (string) $response->json('message', ''));
        }

        return ['id' => (string) $response->json('id'), 'invoice_url' => $url];
    }

    /**
     * IPN signature: HMAC-SHA512 over the JSON body with keys sorted recursively,
     * compared to the x-nowpayments-sig header.
     *
     * @param  array<string, mixed>  $payload
     */
    public function ipnIsAuthentic(GatewayCredentials $credentials, array $payload, ?string $signature): bool
    {
        $secret = $credentials->nowPaymentsIpnSecret;
        if ($secret === null || $signature === null || $signature === '') {
            return false;
        }
        self::ksortRecursive($payload);
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $json, $secret), strtolower($signature));
    }

    /** @param array<array-key, mixed> $array */
    private static function ksortRecursive(array &$array): void
    {
        ksort($array);
        foreach ($array as &$value) {
            if (is_array($value)) {
                self::ksortRecursive($value);
            }
        }
    }
}
