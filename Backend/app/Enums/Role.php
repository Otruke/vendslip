<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Spatie role names. Platform roles (SuperAdmin, Admin, SupportAgent, QAReviewer) access /admin
 * (support staff only see the support console);
 * merchant roles scope access inside a single merchant workspace.
 */
#[TypeScript]
enum Role: string
{
    case SUPER_ADMIN = 'SuperAdmin';
    case ADMIN = 'Admin';
    /** Answers support tickets in the admin portal; no access to merchants, payments or settings. */
    case SUPPORT_AGENT = 'SupportAgent';
    /** Reads every ticket, adds internal notes and scores replies; never writes to customers. */
    case QA_REVIEWER = 'QAReviewer';
    case OWNER = 'Owner';
    case MANAGER = 'Manager';
    case SALES_AGENT = 'SalesAgent';
    case ACCOUNTANT = 'Accountant';

    public function isPlatform(): bool
    {
        return in_array($this, [self::SUPER_ADMIN, self::ADMIN, self::SUPPORT_AGENT, self::QA_REVIEWER], true);
    }

    /** @return list<self> */
    public static function merchantRoles(): array
    {
        return [self::OWNER, self::MANAGER, self::SALES_AGENT, self::ACCOUNTANT];
    }

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Admin',
            self::ADMIN => 'Admin',
            self::SUPPORT_AGENT => 'Support Agent',
            self::QA_REVIEWER => 'QA Reviewer',
            self::OWNER => 'Owner',
            self::MANAGER => 'Manager',
            self::SALES_AGENT => 'Sales Agent',
            self::ACCOUNTANT => 'Accountant',
        };
    }
}
