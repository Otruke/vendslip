<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Data\DocumentDraftData;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\PaymentMethod;
use App\Exceptions\DomainRuleException;
use App\Models\Document;
use App\Models\DocumentPayment;
use App\Models\DocumentRevision;
use App\Models\Merchant;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class DocumentLifecycleService
{
    public function __construct(
        private readonly TotalsCalculator $calculator,
        private readonly VerificationService $verification,
        private readonly InventoryService $inventory,
        private readonly MutabilityGuard $guard,
        private readonly TemplateResolver $templates,
        private readonly DocumentIssuanceService $issuance,
    ) {}

    /**
     * Edit an issued slip under the merchant's mutability policy. Creates a forensic revision
     * (before/after JSON, actor, IP, reason) and re-stamps the verification hash.
     */
    public function edit(Document $document, User $actor, DocumentDraftData $draft, ?string $reason, ?string $ip = null, ?string $userAgent = null): Document
    {
        $verdict = $this->guard->evaluate($document, $actor);
        if (! $verdict['allowed']) {
            throw new DomainRuleException((string) $verdict['message'], 'document_immutable', 403);
        }
        $reason = $reason !== null ? trim($reason) : null;
        if ($verdict['reason_required'] && ($reason === null || mb_strlen($reason) < 5)) {
            throw new DomainRuleException('Please give a reason for this edit (at least 5 characters).', 'reason_required');
        }
        if ($draft->type !== $document->doc_type) {
            throw new DomainRuleException('A receipt cannot become an invoice (or vice versa). Void and reissue instead.', 'type_change');
        }

        $updated = DB::transaction(function () use ($document, $actor, $draft, $reason, $ip, $userAgent): Document {
            /** @var Document $locked */
            $locked = Document::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
            $locked->load('items', 'merchant');
            $merchant = $locked->merchant;
            $before = DocumentSnapshot::of($locked);
            $previousHash = $locked->verification_hash;

            $lines = array_map(static fn ($item): array => [
                'description' => trim($item->description),
                'quantity' => $item->quantity,
                'unit_price_minor' => $item->unit_price_minor,
                'sku_id' => $item->sku_id,
            ], array_values($draft->items));

            $credited = $locked->creditedAmountMinor();
            $vatRate = $draft->vat_rate_bps ?? $locked->vat_rate_bps;
            $vatInclusive = $draft->vat_inclusive ?? $locked->vat_inclusive;
            $preview = $this->calculator->calculate($lines, $draft->discount_minor, $draft->shipping_minor, $vatRate, $vatInclusive, null);
            if ($preview->totalMinor <= 0) {
                throw new DomainRuleException('The slip total must be greater than zero.', 'zero_total');
            }

            if ($locked->doc_type === DocumentType::RECEIPT) {
                // A receipt records what the customer paid: a fully-paid receipt stays fully paid after a correction.
                $paid = $draft->amount_paid_minor
                    ?? ($locked->balance_due_minor === 0 ? $preview->totalMinor - $credited : $locked->amount_paid_minor);
                $paid = max(0, min($paid, $preview->totalMinor));
                if ($paid <= 0) {
                    throw new DomainRuleException('A receipt needs a payment. Use an invoice for unpaid orders.', 'receipt_unpaid');
                }
            } else {
                // Invoice payments live in document_payments; the total can't drop below money already received.
                $paid = $locked->amount_paid_minor;
            }
            if ($preview->totalMinor < $paid + $credited) {
                throw new DomainRuleException(
                    'New total is below what was already paid or credited. Issue a credit note to refund instead.',
                    'total_below_paid',
                );
            }
            $totals = $this->calculator->calculate($lines, $draft->discount_minor, $draft->shipping_minor, $vatRate, $vatInclusive, $paid);

            // Stock: put back what the old version took, then take what the new version needs.
            $oldSkuIds = $locked->items->map(static fn ($i): ?string => $i->sku_id)->values()->all();
            $oldLines = $locked->items->map(static fn ($i): array => ['quantity' => $i->quantity, 'sku_id' => $i->sku_id])->all();
            $this->inventory->apply($merchant, array_values($oldLines), +1);
            $this->inventory->apply($merchant, $lines, -1);

            $template = $draft->template_id !== null
                ? $this->templates->resolve($merchant, $locked->doc_type, $draft->template_id)
                : $locked->template;

            $now = CarbonImmutable::now(config('netslip.timezone'));
            $dueDate = $locked->doc_type === DocumentType::INVOICE && $draft->due_date !== null
                ? CarbonImmutable::createFromFormat('Y-m-d', $draft->due_date, config('netslip.timezone'))?->startOfDay()
                : $locked->due_date;
            $balance = $totals->totalMinor - $paid - $credited;

            $locked->fill([
                'customer_name' => trim($draft->customer_name),
                'customer_phone' => $draft->customer_phone,
                'customer_email' => $draft->customer_email,
                'customer_address' => $draft->customer_address,
                'template_id' => $template?->id,
                'subtotal_minor' => $totals->subtotalMinor,
                'discount_minor' => $totals->discountMinor,
                'vat_rate_bps' => $totals->vatRateBps,
                'vat_inclusive' => $totals->vatInclusive,
                'tax_minor' => $totals->taxMinor,
                'shipping_minor' => $totals->shippingMinor,
                'total_minor' => $totals->totalMinor,
                'amount_paid_minor' => $paid,
                'balance_due_minor' => $balance,
                'status' => $this->statusFor($locked->doc_type, $balance, $paid, $dueDate, $now),
                'notes' => $draft->notes,
                'due_date' => $dueDate,
                'revision_number' => $locked->revision_number + 1,
            ]);
            $locked->save();

            $locked->items()->delete();
            foreach ($totals->lines as $position => $line) {
                $locked->items()->create([...$line, 'position' => $position]);
            }
            $locked->load('items');

            // A save that changes nothing must not add noise to the forensic trail (the transaction rolls back).
            $comparable = static fn (array $snapshot): array => array_diff_key($snapshot, ['revision_number' => true, 'verification_hash' => true]);
            $newSkuIds = $locked->items->map(static fn ($i): ?string => $i->sku_id)->values()->all();
            if ($comparable(DocumentSnapshot::of($locked)) === $comparable($before) && $newSkuIds === $oldSkuIds) {
                throw new DomainRuleException('Nothing changed, so no new revision was saved.', 'no_changes');
            }

            $this->verification->stamp($locked);
            $locked->save();

            DocumentRevision::query()->create([
                'document_id' => $locked->id,
                'revision_number' => $locked->revision_number,
                'actor_user_id' => $actor->id,
                'action' => DocumentRevision::ACTION_EDIT,
                'edit_reason' => $reason,
                'before_snapshot_json' => $before,
                'after_snapshot_json' => DocumentSnapshot::of($locked),
                'previous_verification_hash' => $previousHash,
                'actor_ip' => $ip,
                'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 500) : null,
            ]);

            return $locked;
        });

        $this->issuance->renderQuietly($updated);

        return $updated->refresh()->load('items', 'revisions');
    }

    /** Void a slip (allowed in every mutability mode). Restocks inventory and logs a revision. */
    public function void(Document $document, User $actor, string $reason, ?string $ip = null, ?string $userAgent = null): Document
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw new DomainRuleException('Please give a reason for voiding (at least 5 characters).', 'reason_required');
        }

        $voided = DB::transaction(function () use ($document, $actor, $reason, $ip, $userAgent): Document {
            /** @var Document $locked */
            $locked = Document::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
            if ($locked->isVoid()) {
                throw new DomainRuleException('This slip is already void.', 'already_void');
            }
            $locked->load('items', 'merchant');
            $before = DocumentSnapshot::of($locked);

            if ($locked->doc_type !== DocumentType::CREDIT_NOTE && $locked->parent_document_id === null) {
                $lines = $locked->items->map(static fn ($i): array => ['quantity' => $i->quantity, 'sku_id' => $i->sku_id])->all();
                $this->inventory->apply($locked->merchant, array_values($lines), +1);
            }

            $locked->status = DocumentStatus::VOID;
            $locked->voided_at = CarbonImmutable::now();
            $locked->void_reason = $reason;
            $locked->save();

            DocumentRevision::query()->create([
                'document_id' => $locked->id,
                'revision_number' => $locked->revision_number,
                'actor_user_id' => $actor->id,
                'action' => DocumentRevision::ACTION_VOID,
                'edit_reason' => $reason,
                'before_snapshot_json' => $before,
                'after_snapshot_json' => DocumentSnapshot::of($locked),
                'previous_verification_hash' => $locked->verification_hash,
                'actor_ip' => $ip,
                'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 500) : null,
            ]);

            // A voided credit note restores the parent's balance.
            if ($locked->doc_type === DocumentType::CREDIT_NOTE && $locked->parent_document_id !== null) {
                $parent = Document::query()->whereKey($locked->parent_document_id)->lockForUpdate()->first();
                if ($parent !== null && ! $parent->isVoid()) {
                    $this->recalculateBalance($parent);
                }
            }

            return $locked;
        });

        $this->issuance->renderQuietly($voided);

        return $voided->refresh();
    }

    /** Issue a credit note (full or partial) against a receipt or invoice. */
    public function issueCreditNote(Document $document, User $actor, int $amountMinor, string $reason, ?string $ip = null): Document
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw new DomainRuleException('Please give a reason for the credit note.', 'reason_required');
        }
        if ($document->doc_type === DocumentType::CREDIT_NOTE || $document->isVoid()) {
            throw new DomainRuleException('Credit notes can only be issued against an active receipt or invoice.', 'invalid_parent');
        }

        $credit = DB::transaction(function () use ($document, $actor, $amountMinor, $reason, $ip): Document {
            /** @var Merchant $merchant */
            $merchant = Merchant::query()->whereKey($document->merchant_id)->lockForUpdate()->firstOrFail();
            /** @var Document $parent */
            $parent = Document::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
            $available = $parent->total_minor - $parent->creditedAmountMinor();
            if ($amountMinor <= 0 || $amountMinor > $available) {
                throw new DomainRuleException('Credit amount must be between '.Money::format(1, $parent->currency).' and '.Money::format($available, $parent->currency).'.', 'invalid_amount');
            }
            $before = DocumentSnapshot::of($parent);

            $credit = $this->issuance->issueSystemDocument($merchant, DocumentType::CREDIT_NOTE, [[
                'description' => "Credit against {$parent->doc_number}: {$reason}",
                'quantity' => 1,
                'unit_price_minor' => $amountMinor,
            ]], [
                'customer_id' => $parent->customer_id,
                'template_id' => $parent->template_id,
                'created_by_user_id' => $actor->id,
                'parent_document_id' => $parent->id,
                'channel' => $parent->channel,
                'customer_name' => $parent->customer_name,
                'customer_phone' => $parent->customer_phone,
                'customer_email' => $parent->customer_email,
                'customer_address' => $parent->customer_address,
                'has_watermark' => $parent->has_watermark,
                'notes' => $reason,
            ]);

            $this->recalculateBalance($parent);

            DocumentRevision::query()->create([
                'document_id' => $parent->id,
                'revision_number' => $parent->revision_number,
                'actor_user_id' => $actor->id,
                'action' => DocumentRevision::ACTION_CREDIT_NOTE,
                'edit_reason' => "{$credit->doc_number}: {$reason}",
                'before_snapshot_json' => $before,
                'after_snapshot_json' => DocumentSnapshot::of($parent->refresh()),
                'previous_verification_hash' => $parent->verification_hash,
                'actor_ip' => $ip,
            ]);

            return $credit;
        });

        $this->issuance->renderQuietly($credit);

        return $credit->refresh()->load('items');
    }

    /**
     * Record a payment against an invoice and auto-issue a linked payment receipt (RCP).
     *
     * @return array{invoice: Document, receipt: Document|null, payment: DocumentPayment}
     */
    public function recordPayment(
        Document $invoice,
        int $amountMinor,
        PaymentMethod $method,
        ?string $reference,
        ?User $actor,
        ?CarbonImmutable $paidAt = null,
        ?PaymentTransaction $transaction = null,
        bool $issueReceipt = true,
    ): array {
        if ($invoice->doc_type !== DocumentType::INVOICE) {
            throw new DomainRuleException('Payments can only be recorded against invoices.', 'not_invoice');
        }

        $result = DB::transaction(function () use ($invoice, $amountMinor, $method, $reference, $actor, $paidAt, $transaction, $issueReceipt): array {
            /** @var Merchant $merchant */
            $merchant = Merchant::query()->whereKey($invoice->merchant_id)->lockForUpdate()->firstOrFail();
            /** @var Document $locked */
            $locked = Document::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($locked->isVoid()) {
                throw new DomainRuleException('This invoice is void.', 'invoice_void');
            }
            if ($amountMinor <= 0 || $amountMinor > $locked->balance_due_minor) {
                throw new DomainRuleException('Payment must be between '.Money::format(1, $locked->currency).' and the balance due of '.Money::format($locked->balance_due_minor, $locked->currency).'.', 'invalid_amount');
            }
            $before = DocumentSnapshot::of($locked);
            $paidAt ??= CarbonImmutable::now(config('netslip.timezone'));

            $locked->amount_paid_minor += $amountMinor;
            $this->recalculateBalance($locked);

            $receipt = null;
            if ($issueReceipt) {
                $receipt = $this->issuance->issueSystemDocument($merchant, DocumentType::RECEIPT, [[
                    'description' => "Payment for {$locked->doc_number}".($reference !== null && $reference !== '' ? " (ref {$reference})" : ''),
                    'quantity' => 1,
                    'unit_price_minor' => $amountMinor,
                ]], [
                    'customer_id' => $locked->customer_id,
                    'template_id' => $locked->template_id,
                    'created_by_user_id' => $actor?->id,
                    'parent_document_id' => $locked->id,
                    'channel' => $locked->channel,
                    'customer_name' => $locked->customer_name,
                    'customer_phone' => $locked->customer_phone,
                    'customer_email' => $locked->customer_email,
                    'customer_address' => $locked->customer_address,
                    'has_watermark' => $locked->has_watermark,
                    'notes' => 'Balance remaining on '.$locked->doc_number.': '.Money::format($locked->balance_due_minor, $locked->currency),
                ]);
            }

            $payment = DocumentPayment::query()->create([
                'document_id' => $locked->id,
                'amount_minor' => $amountMinor,
                'method' => $method,
                'reference' => $reference,
                'paid_at' => $paidAt,
                'recorded_by_user_id' => $actor?->id,
                'payment_transaction_id' => $transaction?->id,
                'receipt_document_id' => $receipt?->id,
            ]);

            DocumentRevision::query()->create([
                'document_id' => $locked->id,
                'revision_number' => $locked->revision_number,
                'actor_user_id' => $actor?->id,
                'action' => DocumentRevision::ACTION_PAYMENT,
                'edit_reason' => $method->label().' '.Money::format($amountMinor, $locked->currency).($reference ? " ref {$reference}" : ''),
                'before_snapshot_json' => $before,
                'after_snapshot_json' => DocumentSnapshot::of($locked),
                'previous_verification_hash' => $locked->verification_hash,
            ]);

            return ['invoice' => $locked, 'receipt' => $receipt, 'payment' => $payment];
        });

        $this->issuance->renderQuietly($result['invoice']);
        if ($result['receipt'] !== null) {
            $this->issuance->renderQuietly($result['receipt']);
        }

        return $result;
    }

    /** Recompute balance & status from total, payments and live credit notes (caller holds the lock). */
    public function recalculateBalance(Document $document): void
    {
        $credited = $document->creditedAmountMinor();
        $balance = max(0, $document->total_minor - $document->amount_paid_minor - $credited);
        $document->balance_due_minor = $balance;
        $document->status = $this->statusFor(
            $document->doc_type, $balance, $document->amount_paid_minor, $document->due_date, CarbonImmutable::now(config('netslip.timezone')),
        );
        $document->save();
    }

    private function statusFor(DocumentType $type, int $balance, int $paid, ?CarbonImmutable $dueDate, CarbonImmutable $today): DocumentStatus
    {
        if ($balance === 0) {
            return DocumentStatus::PAID;
        }
        if ($type === DocumentType::INVOICE && $dueDate !== null && $dueDate->toDateString() < $today->toDateString()) {
            return DocumentStatus::OVERDUE;
        }

        return $paid > 0 ? DocumentStatus::PARTIAL : DocumentStatus::UNPAID;
    }
}
