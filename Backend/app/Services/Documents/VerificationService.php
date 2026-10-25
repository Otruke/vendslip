<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\DocumentRevision;
use Illuminate\Support\Str;

/**
 * Tamper-evident slip fingerprints.
 *
 * The HMAC covers the commercial content of a slip (who, what, how much, when, which revision).
 * Payment status is deliberately excluded so recording a payment doesn't invalidate QR codes
 * already sent to customers; the verify portal shows live status from the ledger instead.
 */
final class VerificationService
{
    public function key(): string
    {
        $configured = config('netslip.verification_key');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return hash_hmac('sha256', 'netslip-slip-verification-v1', (string) config('app.key'));
    }

    /** @return array<string, mixed> */
    public function canonicalPayload(Document $document): array
    {
        $document->loadMissing('items');

        return [
            'v' => 1,
            'merchant_id' => $document->merchant_id,
            'doc_type' => $document->doc_type->value,
            'doc_number' => $document->doc_number,
            'currency' => $document->currency,
            'customer_name' => $document->customer_name,
            'items' => $document->items->map(static fn ($item): array => [
                $item->description, $item->quantity, $item->unit_price_minor, $item->line_total_minor,
            ])->values()->all(),
            'subtotal_minor' => $document->subtotal_minor,
            'discount_minor' => $document->discount_minor,
            'tax_minor' => $document->tax_minor,
            'shipping_minor' => $document->shipping_minor,
            'total_minor' => $document->total_minor,
            'issued_at' => $document->issued_at->utc()->format('Y-m-d\TH:i:s\Z'),
            'revision' => $document->revision_number,
        ];
    }

    public function compute(Document $document): string
    {
        $json = json_encode($this->canonicalPayload($document), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return hash_hmac('sha256', $json, $this->key());
    }

    /** Stamp hash + human verification code onto the model (does not save). */
    public function stamp(Document $document): void
    {
        $hash = $this->compute($document);
        $document->verification_hash = $hash;
        $document->verification_code = self::codeFromHash($hash);
    }

    public static function codeFromHash(string $hash): string
    {
        $hex = strtoupper(substr($hash, 0, 12));

        return 'NSV-'.implode('-', str_split($hex, 4));
    }

    public function isIntact(Document $document): bool
    {
        return hash_equals($document->verification_hash, $this->compute($document));
    }

    /**
     * Resolve a QR hash, verification code (NSV-XXXX-XXXX-XXXX) or a superseded hash.
     *
     * @return array{document: Document|null, superseded_by_revision: int|null}
     */
    public function lookup(string $input): array
    {
        $input = trim($input);
        if (preg_match('/^[a-f0-9]{64}$/i', $input) === 1) {
            $hash = strtolower($input);
            $document = Document::query()->where('verification_hash', $hash)->first();
            if ($document !== null) {
                return ['document' => $document, 'superseded_by_revision' => null];
            }
            $revision = DocumentRevision::query()->where('previous_verification_hash', $hash)->orderByDesc('id')->first();
            if ($revision !== null) {
                return ['document' => $revision->document, 'superseded_by_revision' => $revision->document?->revision_number];
            }

            return ['document' => null, 'superseded_by_revision' => null];
        }

        $code = Str::upper((string) preg_replace('/[^A-Za-z0-9]/', '', $input));
        if (str_starts_with($code, 'NSV')) {
            $code = substr($code, 3);
        }
        if (preg_match('/^[A-F0-9]{12}$/', $code) !== 1) {
            return ['document' => null, 'superseded_by_revision' => null];
        }

        return [
            'document' => Document::query()->where('verification_code', 'NSV-'.implode('-', str_split($code, 4)))->first(),
            'superseded_by_revision' => null,
        ];
    }
}
