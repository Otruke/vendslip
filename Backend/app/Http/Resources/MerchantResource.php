<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Merchant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Merchant */
class MerchantResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Merchant $m */
        $m = $this->resource;

        return [
            'id' => $m->id,
            'name' => $m->name,
            'slug' => $m->slug,
            'email' => $m->email,
            'phone' => $m->phone,
            'address' => $m->address,
            'rc_number' => $m->rc_number,
            'tax_id' => $m->tax_id,
            'currency' => $m->currency,
            'vat_rate_bps' => $m->vat_rate_bps,
            'vat_inclusive' => $m->vat_inclusive,
            'primary_color' => $m->primary_color,
            'secondary_color' => $m->secondary_color,
            'logo_url' => $m->logoUrl(),
            'receipt_footer' => $m->receipt_footer,
            'bank_name' => $m->bank_name,
            'bank_account_number' => $m->bank_account_number,
            'bank_account_name' => $m->bank_account_name,
            'invoice_due_days' => $m->invoice_due_days,
            'default_receipt_template_id' => $m->default_receipt_template_id,
            'default_invoice_template_id' => $m->default_invoice_template_id,
            'plan_tier' => $m->plan_tier->value,
            'effective_tier' => $m->effectiveTier()->value,
            'billing_cycle' => $m->billing_cycle?->value,
            'plan_expires_at' => $m->plan_expires_at?->toIso8601String(),
            'paid_credits_balance' => $m->paid_credits_balance,
            'record_mutability_mode' => $m->record_mutability_mode->value,
            'allow_staff_edits' => $m->allow_staff_edits,
            'mutable_window_hours' => $m->mutable_window_hours,
            'status' => $m->status->value,
            'onboarding_completed_at' => $m->onboarding_completed_at?->toIso8601String(),
            'gateways' => [
                'flutterwave' => $m->hasFlutterwave(),
                'flutterwave_public_key' => $m->flutterwave_public_key,
                'nowpayments' => $m->hasNowPayments(),
            ],
        ];
    }
}
