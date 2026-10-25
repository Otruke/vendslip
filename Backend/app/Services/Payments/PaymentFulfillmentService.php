<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\AuditSeverity;
use App\Enums\BillingCycle;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Models\Document;
use App\Models\Merchant;
use App\Models\PaymentTransaction;
use App\Services\Documents\DocumentLifecycleService;
use App\Services\Security\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Applies a verified gateway payment exactly once (row lock + status check), whether it
 * arrives via webhook, IPN, or the browser redirect — whichever comes first wins.
 */
final class PaymentFulfillmentService
{
    public function __construct(
        private readonly DocumentLifecycleService $lifecycle,
        private readonly AuditLogger $audit,
    ) {}

    public function markSuccessful(string $reference, int $paidAmountMinor, string $paidCurrency, ?string $gatewayReference): ?PaymentTransaction
    {
        $result = DB::transaction(function () use ($reference, $paidAmountMinor, $paidCurrency, $gatewayReference): ?array {
            /** @var PaymentTransaction|null $tx */
            $tx = PaymentTransaction::query()->where('reference', $reference)->lockForUpdate()->first();
            if ($tx === null) {
                return null;
            }
            if ($tx->status === PaymentStatus::SUCCESSFUL) {
                return ['tx' => $tx, 'invoice' => null];
            }
            if (strtoupper($paidCurrency) !== strtoupper($tx->currency) || $paidAmountMinor < $tx->amount_minor) {
                $tx->status = PaymentStatus::FAILED;
                $tx->metadata = [...($tx->metadata ?? []), 'failure' => 'amount_or_currency_mismatch', 'paid_minor' => $paidAmountMinor, 'paid_currency' => $paidCurrency];
                $tx->save();
                $this->audit->log('payments.amount_mismatch', null, $tx->merchant_id, ['reference' => $reference, 'expected' => $tx->amount_minor, 'paid' => $paidAmountMinor, 'currency' => $paidCurrency], AuditSeverity::CRITICAL);

                return ['tx' => $tx, 'invoice' => null];
            }

            $tx->status = PaymentStatus::SUCCESSFUL;
            $tx->paid_at = CarbonImmutable::now();
            $tx->gateway_reference = $gatewayReference ?? $tx->gateway_reference;
            $tx->save();

            $invoice = null;
            match ($tx->purpose) {
                PaymentPurpose::SUBSCRIPTION => $this->activatePlan($tx),
                PaymentPurpose::CREDIT_PACK => $this->addCredits($tx),
                PaymentPurpose::INVOICE_PAYMENT => $invoice = $tx->document,
            };

            return ['tx' => $tx, 'invoice' => $invoice];
        });

        if ($result === null) {
            return null;
        }

        // Invoice settlement runs in its own transaction (it renders PDFs after commit).
        if ($result['invoice'] instanceof Document) {
            $this->settleInvoice($result['tx'], $result['invoice']);
        }
        $this->audit->log('payments.successful', null, $result['tx']->merchant_id, ['reference' => $reference, 'purpose' => $result['tx']->purpose->value]);

        return $result['tx'];
    }

    public function markFailed(string $reference, string $reason): void
    {
        PaymentTransaction::query()->where('reference', $reference)->where('status', PaymentStatus::PENDING->value)
            ->update(['status' => PaymentStatus::FAILED->value, 'metadata' => json_encode(['failure' => mb_substr($reason, 0, 120)])]);
    }

    private function activatePlan(PaymentTransaction $tx): void
    {
        /** @var Merchant $merchant */
        $merchant = Merchant::query()->whereKey($tx->merchant_id)->lockForUpdate()->firstOrFail();
        $cycle = $tx->billing_cycle ?? BillingCycle::MONTHLY;
        $base = $merchant->plan_tier === $tx->plan_tier && $merchant->plan_expires_at !== null && $merchant->plan_expires_at->isFuture()
            ? $merchant->plan_expires_at
            : CarbonImmutable::now();
        $merchant->plan_tier = $tx->plan_tier ?? $merchant->plan_tier;
        $merchant->billing_cycle = $cycle;
        $merchant->plan_expires_at = $cycle === BillingCycle::YEARLY ? $base->addYear() : $base->addMonth();
        $merchant->save();
    }

    private function addCredits(PaymentTransaction $tx): void
    {
        /** @var Merchant $merchant */
        $merchant = Merchant::query()->whereKey($tx->merchant_id)->lockForUpdate()->firstOrFail();
        $merchant->paid_credits_balance += (int) $tx->credits;
        $merchant->save();
    }

    private function settleInvoice(PaymentTransaction $tx, Document $invoice): void
    {
        $invoice->refresh();
        $amount = min($tx->amount_minor, $invoice->balance_due_minor);
        if ($amount <= 0 || $invoice->isVoid()) {
            $this->audit->log('payments.invoice_already_settled', null, $tx->merchant_id, ['reference' => $tx->reference, 'invoice' => $invoice->doc_number], AuditSeverity::WARNING);

            return;
        }
        $this->lifecycle->recordPayment(
            $invoice,
            $amount,
            $tx->gateway === PaymentGateway::NOWPAYMENTS ? PaymentMethod::CRYPTO : PaymentMethod::FLUTTERWAVE,
            $tx->gateway_reference ?? $tx->reference,
            null,
            $tx->paid_at,
            $tx,
        );
    }
}
