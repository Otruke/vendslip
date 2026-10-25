<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Merchant;
use App\Services\Settings\PlatformSettings;

/**
 * Which keys to use: platform keys for NetSlip's own sales (plans, credit packs),
 * the merchant's own keys for their customers' invoice payments.
 */
final readonly class GatewayCredentials
{
    public function __construct(
        public ?string $flutterwaveSecretKey,
        public ?string $flutterwaveSecretHash,
        public ?string $nowPaymentsApiKey,
        public ?string $nowPaymentsIpnSecret,
        public ?string $merchantId = null,
    ) {}

    public static function platform(PlatformSettings $settings): self
    {
        return new self(
            $settings->string('flutterwave.secret_key'),
            $settings->string('flutterwave.secret_hash'),
            $settings->string('nowpayments.api_key'),
            $settings->string('nowpayments.ipn_secret'),
        );
    }

    public static function merchant(Merchant $merchant): self
    {
        return new self(
            $merchant->flutterwave_secret_key,
            $merchant->flutterwave_secret_hash,
            $merchant->nowpayments_api_key,
            $merchant->nowpayments_ipn_secret,
            $merchant->id,
        );
    }
}
