<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Template */
class TemplateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Template $t */
        $t = $this->resource;
        $merchant = $request->user()?->merchant;

        return [
            'id' => $t->id,
            'slug' => $t->slug,
            'name' => $t->name,
            'description' => $t->description,
            'engine' => $t->engine,
            'supports_receipt' => $t->supports_receipt,
            'supports_invoice' => $t->supports_invoice,
            'min_plan_tier' => $t->min_plan_tier->value,
            'is_default' => $t->is_default,
            'is_locked' => $merchant !== null && ! $t->isAvailableTo($merchant->effectiveTier()),
        ];
    }
}
