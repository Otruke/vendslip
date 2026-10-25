<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Spatie role names. Platform roles (SuperAdmin, Admin) access /admin;
 * merchant roles scope access inside a single merchant workspace.
 */
#[TypeScript]
enum Role: string
{
    case SUPER_ADMIN = 'SuperAdmin';
    case ADMIN = 'Admin';
    case OWNER = 'Owner';
    case MANAGER = 'Manager';
    case SALES_AGENT = 'SalesAgent';
    case ACCOUNTANT = 'Accountant';

    public function isPlatform(): bool
    {
        return $this === self::SUPER_ADMIN || $this === self::ADMIN;
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
            self::OWNER => 'Owner',
            self::MANAGER => 'Manager',
            self::SALES_AGENT => 'Sales Agent',
            self::ACCOUNTANT => 'Accountant',
        };
    }
}
