<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\Merchant;
use App\Models\User;
use App\Notifications\BusinessAlert;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Sends a business alert to the right people: the roles the owner chose for that alert, by in-app
 * notification and (if switched on) email. Security alerts always include every active Owner, by
 * email too, and can't be switched off. Alerts never break the action that raised them.
 */
final class MerchantAlerts
{
    /**
     * Effective settings for a business, defaults filled in and security rules applied.
     *
     * @return array<string, array{roles: list<string>, email: bool, mandatory: bool, locked_roles: list<string>}>
     */
    public function settings(Merchant $merchant): array
    {
        $stored = is_array($merchant->alert_settings) ? $merchant->alert_settings : [];
        $out = [];
        foreach (AlertCatalog::all() as $event => $def) {
            $own = is_array($stored[$event] ?? null) ? $stored[$event] : [];
            $roles = isset($own['roles']) && is_array($own['roles'])
                ? array_values(array_intersect(AlertCatalog::merchantRoles(), $own['roles']))
                : $def['roles'];
            $email = isset($own['email']) ? (bool) $own['email'] : $def['email'];
            if ($def['mandatory']) {
                $roles = array_values(array_unique([...$def['locked'], ...$roles]));
                $email = true;
            }
            $out[$event] = ['roles' => $roles, 'email' => $email, 'mandatory' => $def['mandatory'], 'locked_roles' => $def['locked']];
        }

        return $out;
    }

    /**
     * Save what the owner picked. Unknown events/roles are dropped; security alerts stay locked on.
     *
     * @param  array<string, mixed>  $input
     */
    public function save(Merchant $merchant, array $input): void
    {
        $clean = [];
        foreach (AlertCatalog::all() as $event => $def) {
            $row = is_array($input[$event] ?? null) ? $input[$event] : [];
            $roles = is_array($row['roles'] ?? null) ? array_values(array_intersect(AlertCatalog::merchantRoles(), $row['roles'])) : $def['roles'];
            $clean[$event] = ['roles' => $roles, 'email' => (bool) ($row['email'] ?? $def['email'])];
        }
        $merchant->forceFill(['alert_settings' => $clean])->save();
    }

    /** @return Collection<int, User> */
    public function recipients(Merchant $merchant, string $event): Collection
    {
        $roles = $this->settings($merchant)[$event]['roles'] ?? [];
        if ($roles === []) {
            return new Collection;
        }

        return User::query()->role($roles)
            ->where('merchant_id', $merchant->id)
            ->where('is_active', true)
            ->get();
    }

    public function send(Merchant $merchant, string $event, string $title, string $body, ?string $path = null, ?User $skip = null): void
    {
        if (! AlertCatalog::exists($event)) {
            return;
        }
        try {
            $setting = $this->settings($merchant)[$event];
            $users = $this->recipients($merchant, $event)
                // The person who just did it doesn't need telling, unless it's a security alert.
                ->reject(static fn (User $u): bool => ! $setting['mandatory'] && $skip !== null && $u->id === $skip->id)
                ->values();
            if ($users->isEmpty()) {
                return;
            }
            Notification::send($users, new BusinessAlert($event, $title, $body, $path, $setting['email'], $merchant->name));
        } catch (Throwable $e) {
            Log::warning('alerts.send_failed', ['event' => $event, 'merchant_id' => $merchant->id, 'error' => $e->getMessage()]);
        }
    }
}
