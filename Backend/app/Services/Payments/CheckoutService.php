<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\BillingCycle;
use App\Enums\DocumentType;
use App\Enums\PaymentGateway;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Enums\PlanTier;
use App\Exceptions\DomainRuleException;
use App\Models\CreditPack;
use App\Models\Document;
use App\Models\Merchant;
use App\Models\PaymentTransaction;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Billing\PlanEntitlements;
use App\Services\Settings\PlatformSettings;
use App\Support\Urls;
use Illuminate\Support\Str;

final class CheckoutService
{
    public function __construct(
        private readonly FlutterwaveClient $flutterwave,
        private readonly NowPaymentsClient $nowPayments,
        private readonly PlatformSettings $settings,
        private readonly PlanEntitlements $entitlements,
    ) {}

    public function subscription(Merchant $merchant, User $user, PlanTier $tier, BillingCycle $cycle, PaymentGateway $gateway): PaymentTransaction
    {
        $plan = SubscriptionPlan::forTier($tier);
        if ($plan === null || ! $plan->is_active || $tier === PlanTier::FREE) {
            throw new DomainRuleException('That plan is not available.', 'plan_unavailable');
        }
        $amount = $plan->priceFor($cycle);
        if ($amount <= 0) {
            throw new DomainRuleException('That plan has no price set.', 'plan_unavailable');
        }

        $transaction = $this->createTransaction($merchant, $user, PaymentPurpose::SUBSCRIPTION, $gateway, $amount, $plan->currency, [
            'plan_tier' => $tier, 'billing_cycle' => $cycle,
        ]);

        return $this->start($transaction, GatewayCredentials::platform($this->settings), "NetSlip {$plan->name} ({$cycle->label()})", $user);
    }

    public function creditPack(Merchant $merchant, User $user, CreditPack $pack, PaymentGateway $gateway): PaymentTransaction
    {
        if (! $pack->is_active) {
            throw new DomainRuleException('That credit pack is not available.', 'pack_unavailable');
        }
        $transaction = $this->createTransaction($merchant, $user, PaymentPurpose::CREDIT_PACK, $gateway, $pack->price_minor, $pack->currency, [
            'credits' => $pack->credits,
        ]);

        return $this->start($transaction, GatewayCredentials::platform($this->settings), "NetSlip {$pack->credits} slip credits", $user);
    }

    /** Customer pays a merchant's invoice through the merchant's own gateway account. */
    public function invoice(Document $invoice, PaymentGateway $gateway, ?string $email): PaymentTransaction
    {
        $merchant = $invoice->merchant;
        if ($invoice->doc_type !== DocumentType::INVOICE || $invoice->isVoid() || $invoice->balance_due_minor <= 0) {
            throw new DomainRuleException('This invoice has nothing left to pay.', 'nothing_due');
        }
        if (! $this->entitlements->has($merchant, PlanEntitlements::PAYMENT_LINKS)) {
            throw new DomainRuleException('Online payment is not enabled for this merchant.', 'payment_links_disabled', 403);
        }

        $transaction = PaymentTransaction::query()->create([
            'merchant_id' => $merchant->id,
            'document_id' => $invoice->id,
            'purpose' => PaymentPurpose::INVOICE_PAYMENT,
            'gateway' => $gateway,
            'status' => PaymentStatus::PENDING,
            'reference' => $this->reference('INVP'),
            'amount_minor' => $invoice->balance_due_minor,
            'currency' => $invoice->currency,
            'metadata' => ['payer_email' => $email, 'doc_number' => $invoice->doc_number],
        ]);

        $customer = [
            'email' => $email ?: ($invoice->customer_email ?: 'customer+'.$invoice->public_uid.'@netslip.app'),
            'name' => $invoice->customer_name,
            'phonenumber' => $invoice->customer_phone,
        ];

        return $this->start($transaction, GatewayCredentials::merchant($merchant), "{$merchant->name} · {$invoice->doc_number}", null, $customer);
    }

    /**
     * @param  array{email: string, name: string, phonenumber?: string|null}|null  $customer
     */
    private function start(PaymentTransaction $transaction, GatewayCredentials $credentials, string $title, ?User $user, ?array $customer = null): PaymentTransaction
    {
        $customer ??= ['email' => (string) $user?->email, 'name' => (string) $user?->name, 'phonenumber' => $user?->phone];
        $isInvoice = $transaction->purpose === PaymentPurpose::INVOICE_PAYMENT;
        $merchantSegment = $isInvoice ? '/merchants/'.$transaction->merchant_id : '';

        if ($transaction->gateway === PaymentGateway::FLUTTERWAVE) {
            $url = $this->flutterwave->createPaymentLink(
                $credentials,
                $transaction->reference,
                $transaction->amount_minor,
                $transaction->currency,
                route('payments.flutterwave.return', ['reference' => $transaction->reference]),
                $customer,
                $title,
                ['netslip_reference' => $transaction->reference],
            );
        } else {
            $successUrl = $isInvoice
                ? Urls::frontend('pay/'.$transaction->document?->public_uid.'?ref='.$transaction->reference)
                : Urls::frontend('dashboard/billing?ref='.$transaction->reference);
            $invoice = $this->nowPayments->createInvoice(
                $credentials,
                $transaction->reference,
                $transaction->amount_minor,
                $transaction->currency,
                $title,
                url('/api/v1/webhooks/nowpayments'.$merchantSegment),
                $successUrl,
                $successUrl.'&cancelled=1',
            );
            $url = $invoice['invoice_url'];
            $transaction->gateway_reference = $invoice['id'];
        }

        $transaction->checkout_url = $url;
        $transaction->save();

        return $transaction;
    }

    /** @param array<string, mixed> $extra */
    private function createTransaction(Merchant $merchant, User $user, PaymentPurpose $purpose, PaymentGateway $gateway, int $amount, string $currency, array $extra): PaymentTransaction
    {
        if ($gateway === PaymentGateway::MANUAL) {
            throw new DomainRuleException('Choose Flutterwave or crypto.', 'invalid_gateway');
        }

        return PaymentTransaction::query()->create([
            'merchant_id' => $merchant->id,
            'initiated_by_user_id' => $user->id,
            'purpose' => $purpose,
            'gateway' => $gateway,
            'status' => PaymentStatus::PENDING,
            'reference' => $this->reference($purpose === PaymentPurpose::SUBSCRIPTION ? 'SUB' : 'CRD'),
            'amount_minor' => $amount,
            'currency' => $currency,
            ...$extra,
        ]);
    }

    private function reference(string $prefix): string
    {
        return 'NS-'.$prefix.'-'.now()->format('ymd').'-'.Str::upper(Str::random(10));
    }
}
