<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DocumentRevision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DocumentRevision */
class RevisionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var DocumentRevision $rev */
        $rev = $this->resource;

        return [
            'id' => $rev->id,
            'revision_number' => $rev->revision_number,
            'action' => $rev->action,
            'edit_reason' => $rev->edit_reason,
            'actor' => $rev->actor?->name,
            'actor_ip' => $rev->actor_ip,
            'created_at' => $rev->created_at->toIso8601String(),
            'diff' => $rev->diff(),
            'before' => $rev->before_snapshot_json,
            'after' => $rev->after_snapshot_json,
        ];
    }
}
