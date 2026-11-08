<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PaymentGateway;
use App\Enums\PaymentPurpose;
use App\Models\PaymentTransaction;
use App\Services\Payments\GatewayCredentials;
use App\Services\Settings\PlatformSettings;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Testing only: sends VendSlip a NOWPayments payment notification (IPN) for a pending crypto
 * checkout, signed with the real IPN secret exactly as NOWPayments signs it. It goes through the
 * public webhook URL, so it tests the tunnel, the signature check and what happens after payment
 * without spending crypto. Refuses to run when APP_ENV=production.
 */
final class SendTestIpn extends Command
{
    protected $signature = 'vendslip:test-ipn
        {reference : The payment reference (VS-…), shown on Billing or in the admin Payments list}
        {--status=finished : finished | partially_paid | failed | expired}
        {--amount= : Amount paid in the checkout currency (defaults to the full amount)}
        {--url= : Base URL to send it to (defaults to APP_URL), e.g. http://127.0.0.1:8000}
        {--dry : Print the signed request instead of sending it}';

    protected $description = 'Testing only: send a signed NOWPayments IPN for a pending crypto checkout';

    public function handle(PlatformSettings $settings): int
    {
        if (app()->environment('production')) {
            $this->error('Not available in production. Make a small real payment instead.');

            return self::FAILURE;
        }
        $tx = PaymentTransaction::query()->where('reference', (string) $this->argument('reference'))->first();
        if ($tx === null) {
            $this->error('No payment with that reference.');

            return self::FAILURE;
        }
        if ($tx->gateway !== PaymentGateway::NOWPAYMENTS) {
            $this->error('That payment was started with '.$tx->gateway->value.', not NOWPayments (crypto).');

            return self::FAILURE;
        }
        $status = (string) $this->option('status');
        if (! in_array($status, ['finished', 'partially_paid', 'failed', 'expired'], true)) {
            $this->error('Status must be finished, partially_paid, failed or expired.');

            return self::FAILURE;
        }

        $isInvoice = $tx->purpose === PaymentPurpose::INVOICE_PAYMENT;
        $credentials = $isInvoice && $tx->merchant !== null ? GatewayCredentials::merchant($tx->merchant) : GatewayCredentials::platform($settings);
        $secret = $credentials->nowPaymentsIpnSecret;
        if ($secret === null) {
            $this->error('No NOWPayments IPN secret is set for '.($isInvoice ? 'this business' : 'VendSlip').'.');

            return self::FAILURE;
        }

        $amount = $this->option('amount') !== null ? (float) $this->option('amount') : round($tx->amount_minor / 100, 2);
        $now = now()->toIso8601String();
        $payload = [
            'payment_id' => random_int(4_000_000_000, 4_999_999_999),
            'invoice_id' => (int) ($tx->gateway_reference ?? 0),
            'payment_status' => $status,
            'pay_address' => 'TEST-ADDRESS',
            'price_amount' => $amount,
            'price_currency' => strtolower($tx->currency),
            'pay_amount' => 10.5,
            'actually_paid' => in_array($status, ['finished', 'partially_paid'], true) ? 10.5 : 0,
            'pay_currency' => 'usdttrc20',
            'order_id' => $tx->reference,
            'order_description' => 'VendSlip test IPN',
            'outcome_amount' => 10.4,
            'outcome_currency' => 'usdttrc20',
            'created_at' => $now,
            'updated_at' => $now,
        ];
        ksort($payload);
        $json = (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha512', $json, $secret);
        $base = rtrim((string) ($this->option('url') ?: config('app.url')), '/');
        $url = $base.'/api/v1/webhooks/nowpayments'.($isInvoice ? '/merchants/'.$tx->merchant_id : '');

        if ($this->option('dry')) {
            $this->line('POST '.$url);
            $this->line('x-nowpayments-sig: '.$signature);
            $this->line($json);

            return self::SUCCESS;
        }

        try {
            $response = Http::withHeaders(['x-nowpayments-sig' => $signature])->withBody($json, 'application/json')->acceptJson()->timeout(20)->post($url);
        } catch (ConnectionException) {
            $this->error("Couldn't reach {$url}. Is the API (and the tunnel) running?");

            return self::FAILURE;
        }
        $this->line("Sent '{$status}' to {$url}: HTTP {$response->status()}");
        $this->line('Payment is now: '.$tx->refresh()->status->value);

        return $response->successful() ? self::SUCCESS : self::FAILURE;
    }
}
