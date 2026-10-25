<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditSeverity;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\PaymentTransaction;
use App\Services\Payments\FlutterwaveClient;
use App\Services\Payments\GatewayCredentials;
use App\Services\Payments\NowPaymentsClient;
use App\Services\Payments\PaymentFulfillmentService;
use App\Services\Security\AuditLogger;
use App\Services\Settings\PlatformSettings;
use App\Support\Urls;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly FlutterwaveClient $flutterwave,
        private readonly NowPaymentsClient $nowPayments,
        private readonly PaymentFulfillmentService $fulfillment,
        private readonly PlatformSettings $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function flutterwave(Request $request, ?string $merchantId = null): JsonResponse
    {
        $credentials = $this->credentials($merchantId);
        if (! $this->flutterwave->webhookIsAuthentic($credentials, $request->header('verif-hash'))) {
            $this->audit->log('webhook.flutterwave_bad_signature', null, $merchantId, ['ip' => $request->ip()], AuditSeverity::CRITICAL);

            return response()->json(['ok' => false], 401);
        }

        $txRef = (string) $request->input('data.tx_ref', $request->input('txRef', ''));
        $transaction = $this->transactionFor($txRef, $merchantId);
        if ($transaction !== null) {
            $this->verifyFlutterwave($transaction, $credentials);
        }

        return response()->json(['ok' => true]);
    }

    /** Browser return from Flutterwave Standard: verify server-side, then bounce to the SPA. */
    public function flutterwaveReturn(Request $request, string $reference): RedirectResponse
    {
        $transaction = PaymentTransaction::query()->where('reference', $reference)->first();
        if ($transaction === null) {
            return redirect()->away(Urls::frontend('/'));
        }
        $isInvoice = $transaction->purpose === PaymentPurpose::INVOICE_PAYMENT;
        $merchant = $transaction->merchant;
        $credentials = $isInvoice && $merchant !== null ? GatewayCredentials::merchant($merchant) : GatewayCredentials::platform($this->settings);

        if ($request->query('status') === 'cancelled') {
            $this->fulfillment->markFailed($reference, 'cancelled_by_customer');
        } else {
            $this->verifyFlutterwave($transaction, $credentials);
        }
        $status = $transaction->refresh()->status->value;

        $target = $isInvoice
            ? 'pay/'.$transaction->document?->public_uid.'?ref='.$reference.'&status='.strtolower($status)
            : 'dashboard/billing?ref='.$reference.'&status='.strtolower($status);

        return redirect()->away(Urls::frontend($target));
    }

    public function nowPayments(Request $request, ?string $merchantId = null): JsonResponse
    {
        $credentials = $this->credentials($merchantId);
        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();
        if (! $this->nowPayments->ipnIsAuthentic($credentials, $payload, $request->header('x-nowpayments-sig'))) {
            $this->audit->log('webhook.nowpayments_bad_signature', null, $merchantId, ['ip' => $request->ip()], AuditSeverity::CRITICAL);

            return response()->json(['ok' => false], 401);
        }

        $reference = (string) ($payload['order_id'] ?? '');
        $transaction = $this->transactionFor($reference, $merchantId);
        if ($transaction === null) {
            return response()->json(['ok' => true]);
        }

        $status = (string) ($payload['payment_status'] ?? '');
        if ($status === 'finished') {
            // price_amount is denominated in our currency; NOWPayments only sends "finished" once fully paid.
            $paidMinor = (int) round(((float) ($payload['price_amount'] ?? 0)) * 100);
            $this->fulfillment->markSuccessful($reference, $paidMinor, (string) ($payload['price_currency'] ?? ''), (string) ($payload['payment_id'] ?? ''));
        } elseif (in_array($status, ['failed', 'expired', 'refunded'], true)) {
            $this->fulfillment->markFailed($reference, 'nowpayments_'.$status);
        }

        return response()->json(['ok' => true]);
    }

    private function verifyFlutterwave(PaymentTransaction $transaction, GatewayCredentials $credentials): void
    {
        if ($transaction->status !== PaymentStatus::PENDING) {
            return;
        }
        $verified = $this->flutterwave->verifyByReference($credentials, $transaction->reference);
        if ($verified === null || $verified['tx_ref'] !== $transaction->reference) {
            return;
        }
        if ($verified['status'] === 'successful') {
            $this->fulfillment->markSuccessful($transaction->reference, $verified['amount_minor'], $verified['currency'], $verified['id']);
        } elseif ($verified['status'] === 'failed') {
            $this->fulfillment->markFailed($transaction->reference, 'flutterwave_failed');
        }
    }

    private function credentials(?string $merchantId): GatewayCredentials
    {
        if ($merchantId === null) {
            return GatewayCredentials::platform($this->settings);
        }
        $merchant = Merchant::query()->find($merchantId);
        abort_if($merchant === null, 404);

        return GatewayCredentials::merchant($merchant);
    }

    /** Merchant-scoped webhooks may only touch that merchant's invoice payments (and vice versa). */
    private function transactionFor(string $reference, ?string $merchantId): ?PaymentTransaction
    {
        if ($reference === '') {
            return null;
        }
        $query = PaymentTransaction::query()->where('reference', $reference);
        if ($merchantId !== null) {
            $query->where('merchant_id', $merchantId)->where('purpose', PaymentPurpose::INVOICE_PAYMENT->value);
        } else {
            $query->where('purpose', '!=', PaymentPurpose::INVOICE_PAYMENT->value);
        }

        return $query->first();
    }
}
