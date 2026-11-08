<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\AuditSeverity;
use App\Enums\BillingCycle;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Enums\PlanTier;
use App\Models\Document;
use App\Models\Merchant;
use App\Models\PaymentTransaction;
use App\Models\SubscriptionPlan;
use App\Services\Documents\DocumentLifecycleService;
use App\Services\Pricing\FxRates;
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
        private readonly FxRates $fx,
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
        $newTier = $tx->plan_tier ?? $merchant->plan_tier;
        $now = CarbonImmutable::now();
        $active = $merchant->plan_tier !== PlanTier::FREE && $merchant->plan_expires_at !== null && $merchant->plan_expires_at->isFuture();

        $start = $now;
        $carried = 0;
        if ($active && $merchant->plan_expires_at !== null) {
            if ($merchant->plan_tier === $newTier) {
                // Renewing the same plan early: the new term starts when the paid one ends.
                $start = $merchant->plan_expires_at;
            } else {
                // Changing plan mid-term: the unused value of the old plan becomes extra time on the new one.
                $carried = $this->carriedSeconds($merchant, $newTier, (int) $now->diffInSeconds($merchant->plan_expires_at));
            }
        }

        $ends = ($cycle === BillingCycle::YEARLY ? $start->addYear() : $start->addMonth())->addSeconds($carried);
        $merchant->plan_tier = $newTier;
        $merchant->billing_cycle = $cycle;
        $merchant->plan_expires_at = $ends;
        $merchant->save();

        if ($carried > 0) {
            $tx->forceFill(['metadata' => [...($tx->metadata ?? []), 'carried_over_days' => round($carried / 86400, 1)]])->save();
            $this->audit->log('billing.plan_changed_with_credit', null, $merchant, [
                'reference' => $tx->reference, 'to' => $newTier->value, 'carried_over_days' => round($carried / 86400, 1),
            ]);
        }
    }

    /**
     * Unused time on the old plan, converted to time on the new plan at the two plans' monthly
     * prices (in the same currency). Moving up gives fewer days, moving down gives more; nothing is
     * ever lost. Without comparable prices no credit is given (and nothing is taken either).
     */
    private function carriedSeconds(Merchant $merchant, PlanTier $newTier, int $remainingSeconds): int
    {
        $old = SubscriptionPlan::forTier($merchant->plan_tier);
        $new = SubscriptionPlan::forTier($newTier);
        if ($old === null || $new === null || $remainingSeconds <= 0) {
            return 0;
        }
        $oldMonthly = $merchant->billing_cycle === BillingCycle::YEARLY ? $old->yearly_price_minor / 12 : $old->monthly_price_minor;
        $newMonthly = $new->monthly_price_minor;
        if ($old->currency !== $new->currency) {
            $oldMonthly = $this->fx->convert((int) round($oldMonthly), $old->currency, $new->currency);
        }
        if ($oldMonthly === null || $oldMonthly <= 0 || $newMonthly <= 0) {
            return 0;
        }

        // Capped at two years so a misconfigured price can't hand out unlimited time.
        return (int) min(2 * 365 * 86400, floor($remainingSeconds * $oldMonthly / $newMonthly));
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
