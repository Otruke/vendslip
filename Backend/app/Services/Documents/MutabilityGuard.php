<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Enums\DocumentType;
use App\Enums\MutabilityMode;
use App\Models\Document;
use App\Models\User;

/**
 * Per-client ledger mutability policy, set by Super-Admin/Admin on each merchant:
 *  IMMUTABLE             -> issued slips are locked; only credit notes / voids
 *  MUTABLE_WITH_AUDIT    -> edits allowed with a mandatory reason, full before/after snapshot
 *                           (optionally limited to mutable_window_hours, staff only if allow_staff_edits)
 *  MUTABLE_UNRESTRICTED  -> edits allowed (still snapshotted for forensics, reason optional)
 */
final class MutabilityGuard
{
    /** @return array{allowed: bool, reason_required: bool, message: string|null} */
    public function evaluate(Document $document, User $actor): array
    {
        $merchant = $document->merchant;
        $deny = static fn (string $message): array => ['allowed' => false, 'reason_required' => true, 'message' => $message];

        if ($actor->merchant_id !== $document->merchant_id) {
            return $deny('You do not have access to this document.');
        }
        if ($document->isVoid()) {
            return $deny('Void documents cannot be edited.');
        }
        if ($document->doc_type === DocumentType::CREDIT_NOTE || $document->parent_document_id !== null) {
            return $deny('System-generated documents cannot be edited.');
        }

        $mode = $merchant->record_mutability_mode;
        if ($mode === MutabilityMode::IMMUTABLE) {
            return $deny('This record is locked. Issue a credit note or void it instead.');
        }

        if (! $actor->isMerchantManager() && ! $merchant->allow_staff_edits) {
            return $deny('Only owners and managers can edit issued slips.');
        }

        if ($mode === MutabilityMode::MUTABLE_WITH_AUDIT && $merchant->mutable_window_hours > 0
            && $document->issued_at->addHours($merchant->mutable_window_hours)->isPast()) {
            return $deny("The {$merchant->mutable_window_hours}-hour edit window for this slip has closed.");
        }

        return [
            'allowed' => true,
            'reason_required' => $mode === MutabilityMode::MUTABLE_WITH_AUDIT,
            'message' => null,
        ];
    }
}
