<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\DocumentType;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\Between;
use Spatie\LaravelData\Attributes\Validation\DateFormat;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Everything needed to issue (or re-issue on edit) a receipt or invoice.
 * Shared by the React composer API, bot confirmations and admin tooling.
 */
#[TypeScript]
final class DocumentDraftData extends Data
{
    /**
     * @param  array<int, DocumentItemData>  $items
     */
    public function __construct(
        #[In(['RECEIPT', 'INVOICE'])]
        public DocumentType $type,
        #[Max(160)]
        public string $customer_name,
        #[DataCollectionOf(DocumentItemData::class), Min(1), Max(100)]
        public array $items,
        #[Max(32)]
        public ?string $customer_phone = null,
        #[Email, Max(255)]
        public ?string $customer_email = null,
        #[Max(500)]
        public ?string $customer_address = null,
        #[Uuid]
        public ?string $customer_id = null,
        #[Min(0), Max(1_000_000_000_000_000)]
        public int $discount_minor = 0,
        #[Min(0), Max(1_000_000_000_000_000)]
        public int $shipping_minor = 0,
        #[Between(0, 10000)]
        public ?int $vat_rate_bps = null,
        public ?bool $vat_inclusive = null,
        #[Min(0), Max(1_000_000_000_000_000)]
        public ?int $amount_paid_minor = null,
        #[DateFormat('Y-m-d')]
        public ?string $due_date = null,
        #[Max(1000)]
        public ?string $notes = null,
        public ?int $template_id = null,
        public bool $use_paid_credit = false,
    ) {}
}
