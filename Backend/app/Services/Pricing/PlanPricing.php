<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Enums\BillingCycle;
use App\Enums\PaymentGateway;
use App\Models\CreditPack;
use App\Models\SubscriptionPlan;
use App\Support\Currencies;

/**
 * Prices plans and credit packs in a business's currency.
 *
 *  1. A fixed price set by the Super-Admin for that currency wins (e.g. ₦ prices stay round).
 *  2. Otherwise the base price (USD recommended) is converted with cached rates and rounded to a
 *     clean amount.
 *  3. Without a rate, the base price in its own currency is used.
 *
 * The amount is fixed on the payment transaction at checkout, so a rate change mid-payment can't
 * change what is charged.
 */
final class PlanPricing
{
    /** Currencies Flutterwave checkout accepts. */
    public const array FLUTTERWAVE_CURRENCIES = ['NGN', 'USD', 'GBP', 'EUR', 'GHS', 'KES', 'ZAR', 'UGX', 'TZS', 'RWF', 'XAF', 'XOF', 'ZMW', 'MWK', 'EGP', 'CAD', 'SLL', 'MZN'];

    /** Fiat currencies NOWPayments can price an invoice in (paid in crypto). */
    public const array NOWPAYMENTS_CURRENCIES = ['USD', 'EUR', 'GBP', 'NGN', 'CAD', 'AUD', 'ZAR'];

    public function __construct(private readonly FxRates $fx) {}

    /**
     * The currency a checkout is charged in: the business's own when the gateway takes it, else USD.
     */
    public static function chargeCurrency(PaymentGateway $gateway, string $wanted): string
    {
        $wanted = strtoupper($wanted);
        $accepted = $gateway === PaymentGateway::NOWPAYMENTS ? self::NOWPAYMENTS_CURRENCIES : self::FLUTTERWAVE_CURRENCIES;

        return in_array($wanted, $accepted, true) ? $wanted : 'USD';
    }

    /** @return array{amount_minor: int, currency: string, source: 'base'|'override'|'converted'} */
    public function plan(SubscriptionPlan $plan, BillingCycle $cycle, string $currency): array
    {
        $currency = strtoupper($currency);
        $key = $cycle === BillingCycle::YEARLY ? 'yearly' : 'monthly';
        $override = $plan->price_overrides[$currency][$key] ?? null;

        return $this->resolve($plan->priceFor($cycle), $plan->currency, $currency, is_int($override) ? $override : null);
    }

    /** @return array{amount_minor: int, currency: string, source: 'base'|'override'|'converted'} */
    public function pack(CreditPack $pack, string $currency): array
    {
        $currency = strtoupper($currency);
        $override = $pack->price_overrides[$currency] ?? null;

        return $this->resolve($pack->price_minor, $pack->currency, $currency, is_int($override) ? $override : null);
    }

    /** @return array{amount_minor: int, currency: string, source: 'base'|'override'|'converted'} */
    private function resolve(int $baseMinor, string $baseCurrency, string $currency, ?int $override): array
    {
        $baseCurrency = strtoupper($baseCurrency);
        if ($currency === $baseCurrency || $baseMinor <= 0) {
            return ['amount_minor' => $baseMinor, 'currency' => $baseCurrency, 'source' => 'base'];
        }
        if ($override !== null && $override > 0) {
            return ['amount_minor' => $override, 'currency' => $currency, 'source' => 'override'];
        }
        $converted = Currencies::exists($currency) ? $this->fx->convert($baseMinor, $baseCurrency, $currency) : null;
        if ($converted === null || $converted <= 0) {
            return ['amount_minor' => $baseMinor, 'currency' => $baseCurrency, 'source' => 'base'];
        }

        return ['amount_minor' => self::clean($converted), 'currency' => $currency, 'source' => 'converted'];
    }

    /**
     * Rounds a converted price up to a clean figure: whole units, then tens, hundreds… as it grows
     * (≈2–3 significant figures), so ₦11,873.42 shows as ₦11,900 and $4.37 as $5.
     */
    public static function clean(int $minor): int
    {
        $major = $minor / 100;
        $step = match (true) {
            $major < 200 => 1,
            $major < 2_000 => 10,
            $major < 20_000 => 100,
            $major < 200_000 => 1_000,
            $major < 2_000_000 => 10_000,
            default => 100_000,
        };

        return (int) (ceil($major / $step) * $step * 100);
    }
}
