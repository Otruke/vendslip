<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum Permission: string
{
    case DOCUMENTS_VIEW = 'documents.view';
    case DOCUMENTS_ISSUE = 'documents.issue';
    case DOCUMENTS_EDIT = 'documents.edit';
    case DOCUMENTS_VOID = 'documents.void';
    case PAYMENTS_RECORD = 'payments.record';
    case CUSTOMERS_MANAGE = 'customers.manage';
    case INVENTORY_MANAGE = 'inventory.manage';
    case TEAM_MANAGE = 'team.manage';
    case SETTINGS_MANAGE = 'settings.manage';
    case BILLING_MANAGE = 'billing.manage';
    case CHANNELS_MANAGE = 'channels.manage';
    case REPORTS_VIEW = 'reports.view';

    /** @return array<string, list<self>> role name => permissions */
    public static function matrix(): array
    {
        $all = self::cases();

        return [
            Role::OWNER->value => $all,
            Role::MANAGER->value => [
                self::DOCUMENTS_VIEW, self::DOCUMENTS_ISSUE, self::DOCUMENTS_EDIT, self::DOCUMENTS_VOID,
                self::PAYMENTS_RECORD, self::CUSTOMERS_MANAGE, self::INVENTORY_MANAGE,
                self::CHANNELS_MANAGE, self::REPORTS_VIEW,
            ],
            Role::SALES_AGENT->value => [
                self::DOCUMENTS_VIEW, self::DOCUMENTS_ISSUE, self::DOCUMENTS_EDIT,
                self::PAYMENTS_RECORD, self::CUSTOMERS_MANAGE,
            ],
            Role::ACCOUNTANT->value => [
                self::DOCUMENTS_VIEW, self::DOCUMENTS_VOID, self::PAYMENTS_RECORD, self::REPORTS_VIEW,
            ],
        ];
    }
}
