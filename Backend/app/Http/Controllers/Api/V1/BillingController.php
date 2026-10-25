<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\BillingCycle;
use App\Enums\PaymentGateway;
use App\Enums\PaymentPurpose;
use App\Enums\Permission;
use App\Enums\PlanTier;
use App\Models\CreditPack;
use App\Models\PaymentTransaction;
use App\Services\Payments\CheckoutService;
use App\Services\Settings\PlatformSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class BillingController extends ApiController
{
    public function __construct(
        private readonly CheckoutService $checkout,
        private readonly PlatformSettings $settings,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $merchant = $this->merchant($request);
        $transactions = PaymentTransaction::query()->where('merchant_id', $merchant->id)
            ->whereIn('purpose', [PaymentPurpose::SUBSCRIPTION->value, PaymentPurpose::CREDIT_PACK->value])
            ->orderByDesc('created_at')->limit(25)->get();

        return response()->json([
            'transactions' => $transactions->map(static fn (PaymentTransaction $t): array => self::transactionArray($t)),
            'gateways' => [
                'flutterwave' => $this->settings->isConfigured('flutterwave.secret_key'),
                'nowpayments' => $this->settings->isConfigured('nowpayments.api_key'),
            ],
        ]);
    }

    public function checkout(Request $request): JsonResponse
    {
        $this->authorizePermission($request, Permission::BILLING_MANAGE);
        $data = $request->validate([
            'purpose' => ['required', Rule::in([PaymentPurpose::SUBSCRIPTION->value, PaymentPurpose::CREDIT_PACK->value])],
            'gateway' => ['required', Rule::in([PaymentGateway::FLUTTERWAVE->value, PaymentGateway::NOWPAYMENTS->value])],
            'plan_tier' => ['required_if:purpose,SUBSCRIPTION', 'nullable', Rule::in([PlanTier::PRO->value, PlanTier::BUSINESS_PLUS->value])],
            'billing_cycle' => ['required_if:purpose,SUBSCRIPTION', 'nullable', Rule::enum(BillingCycle::class)],
            'credit_pack_id' => ['required_if:purpose,CREDIT_PACK', 'nullable', 'integer', 'exists:credit_packs,id'],
        ]);
        $merchant = $this->merchant($request);
        $user = $this->user($request);
        $gateway = PaymentGateway::from($data['gateway']);

        $transaction = $data['purpose'] === PaymentPurpose::SUBSCRIPTION->value
            ? $this->checkout->subscription($merchant, $user, PlanTier::from($data['plan_tier']), BillingCycle::from($data['billing_cycle']), $gateway)
            : $this->checkout->creditPack($merchant, $user, CreditPack::query()->findOrFail((int) $data['credit_pack_id']), $gateway);

        return response()->json(['reference' => $transaction->reference, 'checkout_url' => $transaction->checkout_url], 201);
    }

    public function transaction(Request $request, string $reference): JsonResponse
    {
        $transaction = PaymentTransaction::query()->where('merchant_id', $this->merchant($request)->id)->where('reference', $reference)->firstOrFail();

        return response()->json(self::transactionArray($transaction));
    }

    /** @return array<string, mixed> */
    public static function transactionArray(PaymentTransaction $t): array
    {
        return [
            'reference' => $t->reference,
            'purpose' => $t->purpose->value,
            'gateway' => $t->gateway->value,
            'status' => $t->status->value,
            'amount_minor' => $t->amount_minor,
            'currency' => $t->currency,
            'credits' => $t->credits,
            'plan_tier' => $t->plan_tier?->value,
            'billing_cycle' => $t->billing_cycle?->value,
            'created_at' => $t->created_at->toIso8601String(),
            'paid_at' => $t->paid_at?->toIso8601String(),
        ];
    }
}
