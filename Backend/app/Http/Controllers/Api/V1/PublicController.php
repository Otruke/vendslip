<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\BillingCycle;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Models\CreditPack;
use App\Models\Document;
use App\Models\Merchant;
use App\Models\SubscriptionPlan;
use App\Services\Billing\PlanEntitlements;
use App\Services\Documents\VerificationService;
use App\Services\Marketing\ActivityFeed;
use App\Services\Payments\CheckoutService;
use App\Services\Payouts\PayoutAccounts;
use App\Services\Pdf\SlipRenderer;
use App\Services\Pricing\PlanPricing;
use App\Services\Settings\PlatformSettings;
use App\Support\Countries;
use App\Support\Currencies;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

final class PublicController extends Controller
{
    /** Every currency and country the app supports (bundled data; cacheable by browsers for a day). */
    /** Real, anonymous recent activity for the website's notes (empty when switched off). */
    public function activityFeed(ActivityFeed $feed): JsonResponse
    {
        return response()->json($feed->payload())->setPublic()->setMaxAge(120);
    }

    /** A business's logo (public: it is printed on every slip). */
    public function logo(string $merchantId): Response
    {
        $merchant = Merchant::query()->find($merchantId);
        $path = $merchant?->logoAbsolutePath();
        abort_if($path === null, 404);
        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        return response((string) file_get_contents($path), 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="logo"',
            // Versioned URL (?v=…), so it can be cached for a long time.
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    public function locales(): JsonResponse
    {
        return response()->json([
            'currencies' => array_values(Currencies::all()),
            'countries' => array_values(Countries::all()),
        ])->setPublic()->setMaxAge(86400);
    }

    /** Plans and packs, priced in ?currency= (fixed price, or converted from the base price). */
    public function plans(Request $request, PlatformSettings $settings, PlanPricing $pricing): JsonResponse
    {
        $currency = strtoupper((string) $request->query('currency', ''));
        $currency = Currencies::exists($currency) ? $currency : null;

        return response()->json([
            'currency' => $currency,
            'plans' => SubscriptionPlan::query()->where('is_active', true)->orderBy('sort_order')->get()->map(static function (SubscriptionPlan $p) use ($pricing, $currency): array {
                $monthly = $pricing->plan($p, BillingCycle::MONTHLY, $currency ?? $p->currency);
                $yearly = $pricing->plan($p, BillingCycle::YEARLY, $monthly['currency']);

                return [
                    'tier' => $p->tier->value,
                    'name' => $p->name,
                    'tagline' => $p->tagline,
                    'currency' => $monthly['currency'],
                    'monthly_price_minor' => $monthly['amount_minor'],
                    'yearly_price_minor' => $yearly['currency'] === $monthly['currency'] ? $yearly['amount_minor'] : $p->yearly_price_minor,
                    'converted' => $monthly['source'] === 'converted',
                    'monthly_quota' => $p->monthly_quota,
                    'highlights' => $p->highlights ?? [],
                    'features' => $p->features,
                ];
            }),
            'credit_packs' => CreditPack::query()->where('is_active', true)->orderBy('sort_order')->get()->map(static function (CreditPack $c) use ($pricing, $currency): array {
                $price = $pricing->pack($c, $currency ?? $c->currency);

                return ['id' => $c->id, 'name' => $c->name, 'credits' => $c->credits, 'price_minor' => $price['amount_minor'], 'currency' => $price['currency'], 'converted' => $price['source'] === 'converted'];
            }),
            'yearly_discount_label' => $settings->get('billing.yearly_discount_label'),
            'paid_credit_removes_watermark' => (bool) $settings->get('billing.remove_watermark_on_paid_credit', true),
        ]);
    }

    public function verify(string $code, VerificationService $verification): JsonResponse
    {
        abort_if(mb_strlen($code) > 80, 404);
        $result = $verification->lookup($code);
        $document = $result['document'];
        if ($document === null) {
            return response()->json(['found' => false], 404);
        }
        $document->loadMissing('merchant', 'items');
        $merchant = $document->merchant;
        $intact = $verification->isIntact($document);

        return response()->json([
            'found' => true,
            'authentic' => $intact,
            'superseded' => $result['superseded_by_revision'] !== null,
            'current_revision' => $document->revision_number,
            'document' => [
                'doc_type' => $document->doc_type->value,
                'doc_number' => $document->doc_number,
                'status' => $document->status->value,
                'currency' => $document->currency,
                'total_minor' => $document->total_minor,
                'amount_paid_minor' => $document->amount_paid_minor,
                'balance_due_minor' => $document->balance_due_minor,
                // Data minimisation (NDPA): initials only for the customer, no contact details.
                'customer_display' => self::maskName($document->customer_name),
                'issued_at' => $document->issued_at->toIso8601String(),
                'voided_at' => $document->voided_at?->toIso8601String(),
                'verification_code' => $document->verification_code,
                'items_count' => $document->items->count(),
                'is_mutable_record' => $merchant->record_mutability_mode->value !== 'IMMUTABLE',
            ],
            'merchant' => [
                'name' => $merchant->name,
                'logo_url' => $merchant->logoUrl(),
                'primary_color' => $merchant->primary_color,
                'verified_since' => $merchant->created_at->toDateString(),
            ],
        ]);
    }

    public function invoice(string $uid, PlanEntitlements $entitlements): JsonResponse
    {
        $invoice = $this->findInvoice($uid)->load('items', 'merchant');
        $merchant = $invoice->merchant;
        $online = $entitlements->has($merchant, PlanEntitlements::PAYMENT_LINKS);

        return response()->json([
            'doc_number' => $invoice->doc_number,
            'status' => $invoice->status->value,
            'currency' => $invoice->currency,
            'customer_name' => $invoice->customer_name,
            'issued_at' => $invoice->issued_at->toIso8601String(),
            'due_date' => $invoice->due_date?->toDateString(),
            'items' => $invoice->items->map(static fn ($i): array => [
                'description' => $i->description, 'quantity' => $i->quantity,
                'unit_price_minor' => $i->unit_price_minor, 'line_total_minor' => $i->line_total_minor,
            ]),
            'subtotal_minor' => $invoice->subtotal_minor,
            'discount_minor' => $invoice->discount_minor,
            'tax_minor' => $invoice->tax_minor,
            'shipping_minor' => $invoice->shipping_minor,
            'total_minor' => $invoice->total_minor,
            'amount_paid_minor' => $invoice->amount_paid_minor,
            'balance_due_minor' => $invoice->balance_due_minor,
            'verification_code' => $invoice->verification_code,
            'verification_hash' => $invoice->verification_hash,
            'pdf_url' => URL::temporarySignedRoute('documents.public-pdf', now()->addHours(2), ['uid' => $invoice->public_uid]),
            'merchant' => [
                'name' => $merchant->name,
                'logo_url' => $merchant->logoUrl(),
                'primary_color' => $merchant->primary_color,
                'secondary_color' => $merchant->secondary_color,
                'phone' => $merchant->phone,
                'bank_name' => $merchant->bank_name,
                'bank_account_number' => $merchant->bank_account_number,
                'bank_account_name' => $merchant->bank_account_name,
                'payouts' => PayoutAccounts::forSlips($merchant),
            ],
            'gateways' => [
                'flutterwave' => $online && $merchant->hasFlutterwave(),
                'nowpayments' => $online && $merchant->hasNowPayments(),
            ],
        ]);
    }

    public function payInvoice(Request $request, string $uid, CheckoutService $checkout): JsonResponse
    {
        $data = $request->validate([
            'gateway' => ['required', Rule::in([PaymentGateway::FLUTTERWAVE->value, PaymentGateway::NOWPAYMENTS->value])],
            'email' => ['nullable', 'email', 'max:255'],
        ]);
        $transaction = $checkout->invoice($this->findInvoice($uid), PaymentGateway::from($data['gateway']), $data['email'] ?? null);

        return response()->json(['checkout_url' => $transaction->checkout_url, 'reference' => $transaction->reference]);
    }

    /** Signed, expiring link used by bots (Twilio media URL, Telegram document) and share buttons. */
    public function pdf(Request $request, string $uid, SlipRenderer $renderer): Response
    {
        $document = Document::query()->where('public_uid', $uid)->firstOrFail();

        return response($renderer->content($document), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$document->doc_number.'.pdf"',
            'Cache-Control' => 'private, max-age=300',
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    private function findInvoice(string $uid): Document
    {
        abort_unless(preg_match('/^[a-z0-9]{20,32}$/', $uid) === 1, 404);

        return Document::query()->where('public_uid', $uid)
            ->where('doc_type', DocumentType::INVOICE->value)
            ->where('status', '!=', DocumentStatus::DRAFT->value)
            ->firstOrFail();
    }

    private static function maskName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = $parts[0] ?? '';
        $rest = array_map(static fn (string $p): string => mb_strtoupper(mb_substr($p, 0, 1)).'.', array_slice($parts, 1));

        return trim($first.' '.implode(' ', $rest));
    }
}
