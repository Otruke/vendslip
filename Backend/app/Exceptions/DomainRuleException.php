<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A business rule was violated (quota exhausted, immutable record, invalid state...).
 * Rendered as JSON for the API and as a flash error for Blade admin pages.
 */
class DomainRuleException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'domain_rule',
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }

    public static function quotaExceeded(): self
    {
        return new self(
            'You have used all free slips for this month. Buy a credit pack or upgrade to keep sending.',
            'quota_exceeded',
            402,
        );
    }

    public static function immutable(): self
    {
        return new self(
            'This record is locked. Issue a credit note or void it instead.',
            'document_immutable',
            403,
        );
    }

    public static function featureLocked(string $feature): self
    {
        return new self("Your plan doesn't include {$feature}. Upgrade to unlock it.", 'feature_locked', 403);
    }

    public static function suspended(): self
    {
        return new self('This merchant account is suspended. Contact NetSlip support.', 'merchant_suspended', 423);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'code' => $this->errorCode], $this->status);
    }
}
