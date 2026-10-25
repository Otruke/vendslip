<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Resources\MerchantResource;
use App\Services\Billing\PlanEntitlements;
use App\Services\Security\AuditLogger;
use App\Services\Security\SessionPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class SettingsController extends ApiController
{
    public function __construct(
        private readonly PlanEntitlements $entitlements,
        private readonly AuditLogger $audit,
        private readonly SessionPayload $session,
    ) {}

    public function updateBusiness(Request $request): JsonResponse
    {
        $this->authorizePermission($request, Permission::SETTINGS_MANAGE);
        $merchant = $this->merchant($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:500'],
            'rc_number' => ['nullable', 'string', 'max:64'],
            'tax_id' => ['nullable', 'string', 'max:64'],
            'currency' => ['required', 'string', 'size:3', 'in:NGN,USD,GBP,EUR,GHS,KES,ZAR'],
            'vat_rate_bps' => ['required', 'integer', 'between:0,10000'],
            'vat_inclusive' => ['required', 'boolean'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account_number' => ['nullable', 'string', 'max:32', 'regex:/^[0-9 \-]+$/'],
            'bank_account_name' => ['nullable', 'string', 'max:160'],
            'invoice_due_days' => ['required', 'integer', 'between:0,365'],
            'receipt_footer' => ['nullable', 'string', 'max:280'],
        ]);
        $merchant->update($data);
        $this->audit->log('settings.business_updated', $this->user($request), $merchant, ['fields' => array_keys($merchant->getChanges())]);

        return response()->json(['data' => (new MerchantResource($merchant->refresh()))->resolve()]);
    }

    public function updateBranding(Request $request): JsonResponse
    {
        $this->authorizePermission($request, Permission::SETTINGS_MANAGE);
        $merchant = $this->merchant($request);
        $this->entitlements->ensure($merchant, PlanEntitlements::CUSTOM_BRANDING);
        $data = $request->validate([
            'primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);
        $merchant->update($data);

        return response()->json(['data' => (new MerchantResource($merchant->refresh()))->resolve()]);
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $this->authorizePermission($request, Permission::SETTINGS_MANAGE);
        $merchant = $this->merchant($request);
        $this->entitlements->ensure($merchant, PlanEntitlements::CUSTOM_BRANDING);
        $request->validate(['logo' => ['required', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024', 'dimensions:max_width=2000,max_height=2000']]);

        $file = $request->file('logo');
        abort_unless($file !== null && ! is_array($file), 422);
        $path = $file->storeAs('logos/'.$merchant->id, Str::lower(Str::random(20)).'.'.$file->guessExtension(), 'public');
        abort_if($path === false, 500, 'Could not store the logo.');
        if ($merchant->logo_path !== null) {
            Storage::disk('public')->delete($merchant->logo_path);
        }
        $merchant->update(['logo_path' => $path]);

        return response()->json(['data' => (new MerchantResource($merchant->refresh()))->resolve()]);
    }

    public function deleteLogo(Request $request): JsonResponse
    {
        $this->authorizePermission($request, Permission::SETTINGS_MANAGE);
        $merchant = $this->merchant($request);
        if ($merchant->logo_path !== null) {
            Storage::disk('public')->delete($merchant->logo_path);
            $merchant->update(['logo_path' => null]);
        }

        return response()->json(['data' => (new MerchantResource($merchant->refresh()))->resolve()]);
    }

    public function updateGateways(Request $request): JsonResponse
    {
        $this->authorizePermission($request, Permission::BILLING_MANAGE);
        $merchant = $this->merchant($request);
        $this->entitlements->ensure($merchant, PlanEntitlements::PAYMENT_LINKS);
        $data = $request->validate([
            'flutterwave_public_key' => ['nullable', 'string', 'max:255', 'regex:/^FLWPUBK(_TEST)?-[A-Za-z0-9\-]+$/'],
            'flutterwave_secret_key' => ['nullable', 'string', 'max:255', 'regex:/^FLWSECK(_TEST)?-[A-Za-z0-9\-]+$/'],
            'flutterwave_secret_hash' => ['nullable', 'string', 'min:8', 'max:255'],
            'nowpayments_api_key' => ['nullable', 'string', 'max:255'],
            'nowpayments_ipn_secret' => ['nullable', 'string', 'max:255'],
            'clear' => ['nullable', 'array'],
            'clear.*' => ['in:flutterwave,nowpayments'],
        ]);

        // Only overwrite secrets that were actually sent, so the UI never needs to read them back.
        $updates = array_filter($data, static fn ($v, $k): bool => $k !== 'clear' && $v !== null && $v !== '', ARRAY_FILTER_USE_BOTH);
        foreach ((array) ($data['clear'] ?? []) as $gateway) {
            $keys = $gateway === 'flutterwave'
                ? ['flutterwave_public_key', 'flutterwave_secret_key', 'flutterwave_secret_hash']
                : ['nowpayments_api_key', 'nowpayments_ipn_secret'];
            foreach ($keys as $key) {
                $updates[$key] = null;
            }
        }
        $merchant->update($updates);
        $this->audit->log('settings.gateways_updated', $this->user($request), $merchant, ['keys' => array_keys($updates)]);

        return response()->json(['data' => (new MerchantResource($merchant->refresh()))->resolve()]);
    }

    public function completeOnboarding(Request $request): JsonResponse
    {
        $merchant = $this->merchant($request);
        if ($merchant->onboarding_completed_at === null) {
            $merchant->update(['onboarding_completed_at' => now()]);
        }

        return response()->json($this->session->for($this->user($request)->refresh()));
    }
}
