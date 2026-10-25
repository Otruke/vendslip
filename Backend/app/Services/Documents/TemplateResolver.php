<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Enums\DocumentType;
use App\Models\Merchant;
use App\Models\Template;

final class TemplateResolver
{
    /**
     * Pick the template for a new document: explicit choice (if allowed for the plan and type)
     * -> merchant default for the type -> platform default -> first active template.
     */
    public function resolve(Merchant $merchant, DocumentType $type, int|string|null $requested = null): ?Template
    {
        $tier = $merchant->effectiveTier();
        $supports = static fn (Template $t): bool => $type === DocumentType::INVOICE ? $t->supports_invoice : $t->supports_receipt;

        $candidates = [];
        if ($requested !== null && $requested !== '') {
            $candidates[] = is_int($requested) || ctype_digit((string) $requested)
                ? Template::query()->find((int) $requested)
                : Template::query()->where('slug', (string) $requested)->first();
        }
        $candidates[] = $type === DocumentType::INVOICE ? $merchant->defaultInvoiceTemplate : $merchant->defaultReceiptTemplate;
        $candidates[] = Template::query()->active()->where('is_default', true)->first();

        foreach ($candidates as $template) {
            if ($template instanceof Template && $template->isAvailableTo($tier) && $supports($template)) {
                return $template;
            }
        }

        return Template::query()->active()->get()->first(
            static fn (Template $t): bool => $t->isAvailableTo($tier) && $supports($t),
        );
    }
}
