<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Data\DocumentDraftData;
use App\Enums\Channel;
use App\Enums\DocumentType;
use App\Enums\PaymentMethod;
use App\Exceptions\DomainRuleException;
use App\Models\Customer;
use App\Models\Document;
use App\Models\DocumentPayment;
use App\Models\Merchant;
use App\Models\User;
use App\Services\Billing\QuotaService;
use App\Services\Pdf\SlipRenderer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class DocumentIssuanceService
{
    public function __construct(
        private readonly TotalsCalculator $calculator,
        private readonly QuotaService $quota,
        private readonly DocumentNumberGenerator $numbers,
        private readonly VerificationService $verification,
        private readonly TemplateResolver $templates,
        private readonly InventoryService $inventory,
        private readonly SlipRenderer $renderer,
    ) {}

    /**
     * Issue a receipt or invoice. Atomic: quota charge, gapless number, stock deduction,
     * customer upsert, document + items, verification hash. The PDF is rendered after commit.
     */
    public function issue(Merchant $merchant, ?User $actor, DocumentDraftData $draft, Channel $channel = Channel::WEB): Document
    {
        if ($draft->type === DocumentType::CREDIT_NOTE) {
            throw new DomainRuleException('Credit notes are issued from an existing document.', 'invalid_type');
        }

        $document = DB::transaction(function () use ($merchant, $actor, $draft, $channel): Document {
            /** @var Merchant $locked */
            $locked = Merchant::query()->whereKey($merchant->id)->lockForUpdate()->firstOrFail();
            $now = CarbonImmutable::now(config('netslip.timezone'));

            $lines = array_map(static fn ($item): array => [
                'description' => trim($item->description),
                'quantity' => $item->quantity,
                'unit_price_minor' => $item->unit_price_minor,
                'sku_id' => $item->sku_id,
            ], array_values($draft->items));

            $totals = $this->calculator->calculate(
                lines: $lines,
                discountMinor: $draft->discount_minor,
                shippingMinor: $draft->shipping_minor,
                vatRateBps: $draft->vat_rate_bps ?? $locked->vat_rate_bps,
                vatInclusive: $draft->vat_inclusive ?? $locked->vat_inclusive,
                amountPaidMinor: null,
            );
            if ($totals->totalMinor <= 0) {
                throw new DomainRuleException('The slip total must be greater than zero.', 'zero_total');
            }

            $paid = $draft->amount_paid_minor ?? ($draft->type === DocumentType::RECEIPT ? $totals->totalMinor : 0);
            if ($draft->type === DocumentType::RECEIPT && $paid <= 0) {
                throw new DomainRuleException('A receipt needs a payment. Use an invoice for unpaid orders.', 'receipt_unpaid');
            }
            $totals = $this->calculator->calculate(
                $lines, $draft->discount_minor, $draft->shipping_minor, $totals->vatRateBps, $totals->vatInclusive, $paid,
            );

            $this->inventory->apply($locked, $lines, -1);
            $charge = $this->quota->charge($locked, $draft->use_paid_credit);
            $template = $this->templates->resolve($locked, $draft->type, $draft->template_id);
            $customer = $this->upsertCustomer($locked, $draft);

            $dueDate = null;
            if ($draft->type === DocumentType::INVOICE) {
                $dueDate = $draft->due_date !== null
                    ? CarbonImmutable::createFromFormat('Y-m-d', $draft->due_date, config('netslip.timezone'))?->startOfDay()
                    : $now->startOfDay()->addDays($locked->invoice_due_days);
            }

            $document = new Document([
                'merchant_id' => $locked->id,
                'customer_id' => $customer->id,
                'template_id' => $template?->id,
                'created_by_user_id' => $actor?->id,
                'doc_type' => $draft->type,
                'doc_number' => $this->numbers->next($locked->id, $draft->type, (int) $now->format('Y')),
                'public_uid' => Str::lower(Str::random(26)),
                'status' => $totals->statusFor($draft->type, $dueDate, $now),
                'channel' => $channel,
                'currency' => $locked->currency,
                'customer_name' => trim($draft->customer_name),
                'customer_phone' => $draft->customer_phone,
                'customer_email' => $draft->customer_email,
                'customer_address' => $draft->customer_address,
                'subtotal_minor' => $totals->subtotalMinor,
                'discount_minor' => $totals->discountMinor,
                'vat_rate_bps' => $totals->vatRateBps,
                'vat_inclusive' => $totals->vatInclusive,
                'tax_minor' => $totals->taxMinor,
                'shipping_minor' => $totals->shippingMinor,
                'total_minor' => $totals->totalMinor,
                'amount_paid_minor' => $totals->amountPaidMinor,
                'balance_due_minor' => $totals->balanceDueMinor,
                'consumed_paid_credit' => $charge->consumedPaidCredit,
                'has_watermark' => $charge->hasWatermark,
                'revision_number' => 1,
                'notes' => $draft->notes,
                'due_date' => $dueDate,
                'issued_at' => $now,
            ]);
            $document->verification_hash = str_repeat('0', 64); // placeholder until items exist
            $document->verification_code = 'PENDING-'.Str::upper(Str::random(8));
            $document->save();

            foreach ($totals->lines as $position => $line) {
                $document->items()->create([...$line, 'position' => $position]);
            }
            $document->load('items');

            $this->verification->stamp($document);
            $document->save();

            if ($draft->type === DocumentType::INVOICE && $totals->amountPaidMinor > 0) {
                DocumentPayment::query()->create([
                    'document_id' => $document->id,
                    'amount_minor' => $totals->amountPaidMinor,
                    'method' => PaymentMethod::OTHER,
                    'reference' => 'Deposit at issue',
                    'paid_at' => $now,
                    'recorded_by_user_id' => $actor?->id,
                ]);
            }

            return $document;
        });

        $this->renderQuietly($document);

        return $document->refresh()->load('items');
    }

    /**
     * Allocate a number and persist a system-generated document (payment receipts, credit notes).
     * Caller must already be inside a transaction holding the merchant lock. No quota is charged.
     *
     * @param  list<array{description: string, quantity: int, unit_price_minor: int, sku_id?: string|null}>  $lines
     * @param  array<string, mixed>  $attributes
     */
    public function issueSystemDocument(Merchant $merchant, DocumentType $type, array $lines, array $attributes): Document
    {
        $now = CarbonImmutable::now(config('netslip.timezone'));
        $totals = $this->calculator->calculate($lines, 0, 0, 0, false, $type === DocumentType::INVOICE ? 0 : PHP_INT_MAX);

        $document = new Document([
            ...$attributes,
            'merchant_id' => $merchant->id,
            'doc_type' => $type,
            'doc_number' => $this->numbers->next($merchant->id, $type, (int) $now->format('Y')),
            'public_uid' => Str::lower(Str::random(26)),
            'status' => $totals->statusFor($type),
            'currency' => $merchant->currency,
            'subtotal_minor' => $totals->subtotalMinor,
            'discount_minor' => 0,
            'vat_rate_bps' => 0,
            'vat_inclusive' => false,
            'tax_minor' => 0,
            'shipping_minor' => 0,
            'total_minor' => $totals->totalMinor,
            'amount_paid_minor' => $totals->amountPaidMinor,
            'balance_due_minor' => $totals->balanceDueMinor,
            'consumed_paid_credit' => false,
            'revision_number' => 1,
            'issued_at' => $now,
        ]);
        $document->verification_hash = str_repeat('0', 64);
        $document->verification_code = 'PENDING-'.Str::upper(Str::random(8));
        $document->save();
        foreach ($totals->lines as $position => $line) {
            $document->items()->create([...$line, 'position' => $position]);
        }
        $document->load('items');
        $this->verification->stamp($document);
        $document->save();

        return $document;
    }

    public function renderQuietly(Document $document): void
    {
        try {
            $this->renderer->store($document);
        } catch (Throwable $e) {
            // The ledger entry is authoritative; the PDF is re-rendered on demand.
            report($e);
        }
    }

    private function upsertCustomer(Merchant $merchant, DocumentDraftData $draft): Customer
    {
        if ($draft->customer_id !== null) {
            $customer = Customer::query()->forMerchant($merchant)->find($draft->customer_id);
            if ($customer === null) {
                throw new DomainRuleException('Customer not found.', 'customer_not_found', 404);
            }

            return $customer;
        }

        $query = Customer::query()->forMerchant($merchant);
        $existing = null;
        if (filled($draft->customer_phone)) {
            $existing = (clone $query)->where('phone', $draft->customer_phone)->first();
        }
        if ($existing === null && filled($draft->customer_email)) {
            $existing = (clone $query)->where('email', $draft->customer_email)->first();
        }

        if ($existing !== null) {
            $existing->fill(array_filter([
                'address' => $draft->customer_address,
                'email' => $draft->customer_email,
            ], static fn (?string $v): bool => filled($v)))->save();

            return $existing;
        }

        return Customer::query()->create([
            'merchant_id' => $merchant->id,
            'name' => trim($draft->customer_name),
            'phone' => $draft->customer_phone,
            'email' => $draft->customer_email,
            'address' => $draft->customer_address,
        ]);
    }
}
