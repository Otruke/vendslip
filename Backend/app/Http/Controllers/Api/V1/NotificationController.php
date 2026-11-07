<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Enums\Role;
use App\Services\Billing\PlanEntitlements;
use App\Services\Notifications\AlertCatalog;
use App\Services\Notifications\MerchantAlerts;
use App\Services\Reminders\InvoiceReminders;
use App\Services\Security\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/** The in-app bell (your own notifications) and the business's alert settings. */
final class NotificationController extends ApiController
{
    public function __construct(
        private readonly MerchantAlerts $alerts,
        private readonly AuditLogger $audit,
        private readonly PlanEntitlements $entitlements,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $items = $user->notifications()->latest()->limit(30)->get();

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'data' => $items->map(static fn (DatabaseNotification $n): array => [
                'id' => $n->id,
                'event' => $n->data['event'] ?? null,
                'title' => (string) ($n->data['title'] ?? ''),
                'body' => (string) ($n->data['body'] ?? ''),
                'path' => $n->data['path'] ?? null,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function read(Request $request, string $id): JsonResponse
    {
        $this->user($request)->notifications()->whereKey($id)->firstOrFail()->markAsRead();

        return response()->json(['ok' => true]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $this->user($request)->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function settings(Request $request): JsonResponse
    {
        $merchant = $this->merchant($request);

        return response()->json([
            'events' => collect(AlertCatalog::all())->map(fn (array $def, string $event): array => [
                'event' => $event,
                'label' => $def['label'],
                'description' => $def['description'],
                'group' => $def['group'],
                'mandatory' => $def['mandatory'],
                ...$this->alerts->settings($merchant)[$event],
            ])->values(),
            'roles' => array_map(static fn (string $r): array => ['value' => $r, 'label' => Role::from($r)->label()], AlertCatalog::merchantRoles()),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $this->authorizePermission($request, Permission::SETTINGS_MANAGE);
        $merchant = $this->merchant($request);
        $data = $request->validate([
            'events' => ['required', 'array'],
            'events.*.roles' => ['array'],
            'events.*.roles.*' => ['string'],
            'events.*.email' => ['boolean'],
        ]);
        $this->alerts->save($merchant, $data['events']);
        $this->audit->log('settings.alerts_updated', $this->user($request), $merchant);

        return $this->settings($request);
    }

    /** Due-date reminder emails to customers (Business Plus) and their timing. */
    public function reminders(Request $request): JsonResponse
    {
        $merchant = $this->merchant($request);

        return response()->json([
            'settings' => InvoiceReminders::settings($merchant),
            'available' => $this->entitlements->has($merchant, PlanEntitlements::DEBT_REMINDERS),
        ]);
    }

    public function updateReminders(Request $request): JsonResponse
    {
        $this->authorizePermission($request, Permission::SETTINGS_MANAGE);
        $merchant = $this->merchant($request);
        $data = $request->validate([
            'customer_emails' => ['required', 'boolean'],
            'days_before' => ['required', 'integer', 'between:0,14'],
            'on_due_date' => ['required', 'boolean'],
            'overdue_every_days' => ['required', 'integer', 'between:0,30'],
            'overdue_max' => ['required', 'integer', 'between:0,6'],
        ]);
        $merchant->forceFill(['reminder_settings' => $data])->save();
        $this->audit->log('settings.reminders_updated', $this->user($request), $merchant, $data);

        return $this->reminders($request);
    }
}
