<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Customer */
class CustomerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Customer $c */
        $c = $this->resource;
        $attrs = $c->getAttributes();

        return [
            'id' => $c->id,
            'name' => $c->name,
            'phone' => $c->phone,
            'email' => $c->email,
            'address' => $c->address,
            'notes' => $c->notes,
            'documents_count' => isset($attrs['documents_count']) ? (int) $attrs['documents_count'] : null,
            'total_billed_minor' => isset($attrs['total_billed_minor']) ? (int) $attrs['total_billed_minor'] : null,
            'outstanding_minor' => isset($attrs['outstanding_minor']) ? (int) $attrs['outstanding_minor'] : null,
            'last_purchase_at' => isset($attrs['last_purchase_at']) ? (string) $attrs['last_purchase_at'] : null,
            'created_at' => $c->created_at->toIso8601String(),
        ];
    }
}
