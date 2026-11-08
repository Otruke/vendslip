<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Enums\Role;

/**
 * Every business alert VendSlip can raise, who gets it by default, and whether it is a security alert.
 *
 * Security alerts (mandatory) always reach every active Owner by in-app AND email, whatever the
 * settings say: an owner must always learn when chats are linked to the account, payout details
 * change or VendSlip support enters the dashboard. Owners can add other roles to any alert.
 */
final class AlertCatalog
{
    public const string SLIP_ISSUED = 'slip_issued';

    public const string LOW_STOCK = 'low_stock';

    public const string CHANNEL_LINKED = 'channel_linked';

    public const string PAYOUT_CHANGED = 'payout_account_changed';

    public const string INVOICE_OVERDUE = 'invoice_overdue';

    public const string SUPPORT_SESSION = 'support_session';

    public const string TICKET_UPDATE = 'ticket_update';

    public const string PLAN_RENEWAL = 'plan_renewal';

    public const string LEGAL_UPDATE = 'legal_update';

    /**
     * @return array<string, array{label: string, description: string, group: string, mandatory: bool, locked: list<string>, roles: list<string>, email: bool}>
     */
    public static function all(): array
    {
        return [
            self::SLIP_ISSUED => [
                'label' => 'New slip issued', 'group' => 'Sales',
                'description' => 'A receipt or invoice was issued from the dashboard or a chat.',
                'mandatory' => false, 'locked' => [], 'roles' => [Role::OWNER->value], 'email' => false,
            ],
            self::PLAN_RENEWAL => [
                'label' => 'Plan renewal due', 'group' => 'Billing',
                'description' => 'Your VendSlip plan is about to end or has ended. Plans don’t renew automatically.',
                // Owners always hear about it: a lapsed plan quietly switches the business to Free.
                'mandatory' => true, 'locked' => [Role::OWNER->value], 'roles' => [Role::OWNER->value], 'email' => true,
            ],
            self::LEGAL_UPDATE => [
                'label' => 'Terms or Privacy Policy updated', 'group' => 'Billing',
                'description' => 'A new version of VendSlip’s Terms of Service or Privacy Policy, what changed and when it applies.',
                // The law expects account holders to be told about material changes, so Owners can't turn it off.
                'mandatory' => true, 'locked' => [Role::OWNER->value], 'roles' => [Role::OWNER->value], 'email' => true,
            ],
            self::INVOICE_OVERDUE => [
                'label' => 'Invoice overdue', 'group' => 'Sales',
                'description' => 'An unpaid invoice passed its due date.',
                'mandatory' => false, 'locked' => [], 'roles' => [Role::OWNER->value, Role::ACCOUNTANT->value], 'email' => true,
            ],
            self::LOW_STOCK => [
                'label' => 'Low stock', 'group' => 'Inventory',
                'description' => 'A product dropped to its low-stock level.',
                'mandatory' => false, 'locked' => [], 'roles' => [Role::OWNER->value, Role::MANAGER->value], 'email' => true,
            ],
            self::CHANNEL_LINKED => [
                'label' => 'Chat channel linked', 'group' => 'Security',
                'description' => 'Someone linked WhatsApp, Telegram, Messenger or Instagram to this business.',
                'mandatory' => true, 'locked' => [Role::OWNER->value], 'roles' => [Role::OWNER->value], 'email' => true,
            ],
            self::PAYOUT_CHANGED => [
                'label' => 'Bank or crypto account changed', 'group' => 'Security',
                'description' => 'A bank account or crypto wallet printed on slips was added, edited or removed.',
                // Owners and Managers always hear about it: a changed account number is how payments get diverted.
                'mandatory' => true, 'locked' => [Role::OWNER->value, Role::MANAGER->value], 'roles' => [Role::OWNER->value, Role::MANAGER->value], 'email' => true,
            ],
            self::SUPPORT_SESSION => [
                'label' => 'VendSlip support signed in', 'group' => 'Security',
                'description' => 'VendSlip support opened this dashboard with an owner-approved code.',
                'mandatory' => true, 'locked' => [Role::OWNER->value], 'roles' => [Role::OWNER->value], 'email' => true,
            ],
        ];
    }

    /**
     * Roles a business can choose from (platform roles never belong to a business).
     *
     * @return list<string>
     */
    public static function merchantRoles(): array
    {
        return [Role::OWNER->value, Role::MANAGER->value, Role::SALES_AGENT->value, Role::ACCOUNTANT->value];
    }

    public static function exists(string $event): bool
    {
        return isset(self::all()[$event]);
    }
}
