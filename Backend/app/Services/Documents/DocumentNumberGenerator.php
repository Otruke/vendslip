<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Enums\DocumentType;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Gapless, per-merchant, per-type, per-year sequence: RCP-2026-0001, INV-2026-0001, CRN-2026-0001.
 * The sequence row is locked with SELECT ... FOR UPDATE, so concurrent issuers serialise and a
 * rolled-back issuance also rolls back its number (no gaps).
 */
final class DocumentNumberGenerator
{
    public function next(string $merchantId, DocumentType $type, int $year): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Document numbers must be allocated inside a database transaction.');
        }

        DB::table('document_sequences')->insertOrIgnore([
            'merchant_id' => $merchantId,
            'doc_type' => $type->value,
            'year' => $year,
            'last_number' => 0,
        ]);

        $row = DB::table('document_sequences')
            ->where(['merchant_id' => $merchantId, 'doc_type' => $type->value, 'year' => $year])
            ->lockForUpdate()
            ->first(['last_number']);

        $number = (int) ($row->last_number ?? 0) + 1;

        DB::table('document_sequences')
            ->where(['merchant_id' => $merchantId, 'doc_type' => $type->value, 'year' => $year])
            ->update(['last_number' => $number]);

        return sprintf('%s-%d-%s', $type->prefix(), $year, str_pad((string) $number, 4, '0', STR_PAD_LEFT));
    }
}
