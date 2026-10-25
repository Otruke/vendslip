<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Services\Documents\InventoryService;
use App\Services\Documents\TotalsCalculator;
use App\Services\Slash\DraftPayload;
use App\Services\Slash\SlashCommandParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Server-side twin of the React composer: parses /slash text exactly as the bots do,
 * resolves SKUs from inventory, and returns authoritative totals.
 */
final class ComposerController extends ApiController
{
    public function __construct(
        private readonly SlashCommandParser $parser,
        private readonly InventoryService $inventory,
        private readonly TotalsCalculator $calculator,
    ) {}

    public function parse(Request $request): JsonResponse
    {
        $this->authorizePermission($request, Permission::DOCUMENTS_ISSUE);
        $data = $request->validate([
            'text' => ['required', 'string', 'max:5000'],
            'payload' => ['nullable', 'array'],
        ]);
        $merchant = $this->merchant($request);

        $payload = DraftPayload::fromArray((array) ($data['payload'] ?? []));
        [$payload, $errors] = $payload->apply($this->parser->parse($data['text']));
        $payload = $payload->withItems($this->inventory->resolveDraftItems($merchant, $payload->items()));

        $totals = null;
        if ($payload->isReady()) {
            $draft = $payload->toDraftData();
            $lines = array_values(array_map(static fn ($i): array => ['description' => $i->description, 'quantity' => $i->quantity, 'unit_price_minor' => $i->unit_price_minor], $draft->items));
            $t = $this->calculator->calculate(
                $lines, $draft->discount_minor, $draft->shipping_minor,
                $draft->vat_rate_bps ?? $merchant->vat_rate_bps,
                $draft->vat_inclusive ?? $merchant->vat_inclusive,
                $draft->amount_paid_minor,
            );
            $totals = [
                'subtotal_minor' => $t->subtotalMinor, 'discount_minor' => $t->discountMinor, 'tax_minor' => $t->taxMinor,
                'shipping_minor' => $t->shippingMinor, 'total_minor' => $t->totalMinor, 'vat_rate_bps' => $t->vatRateBps,
            ];
        }

        return response()->json([
            'payload' => $payload->toArray(),
            'errors' => $errors,
            'missing' => $payload->missing(),
            'ready' => $payload->isReady(),
            'totals' => $totals,
        ]);
    }
}
