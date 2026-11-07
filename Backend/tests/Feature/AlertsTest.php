<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Exceptions\DomainRuleException;
use App\Models\InventorySku;
use App\Models\Merchant;
use App\Models\User;
use App\Notifications\BusinessAlert;
use App\Services\Documents\InventoryService;
use App\Services\Notifications\AlertCatalog;
use App\Services\Notifications\MerchantAlerts;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Role-based business alerts, the bell, and the separate stock switches. */
final class AlertsTest extends TestCase
{
    private function staff(Merchant $merchant, Role $role): User
    {
        $user = User::factory()->create(['merchant_id' => $merchant->id]);
        $user->assignRole($role->value);

        return $user;
    }

    public function test_security_alerts_always_reach_owners_by_email(): void
    {
        [$merchant, $owner] = $this->merchantWithOwner();
        $alerts = app(MerchantAlerts::class);
        // An attempt to switch a security alert off is ignored.
        $alerts->save($merchant, [AlertCatalog::CHANNEL_LINKED => ['roles' => [], 'email' => false]]);
        $setting = $alerts->settings($merchant->refresh())[AlertCatalog::CHANNEL_LINKED];
        $this->assertContains(Role::OWNER->value, $setting['roles']);
        $this->assertTrue($setting['email']);

        Notification::fake();
        $alerts->send($merchant, AlertCatalog::CHANNEL_LINKED, 'Telegram linked', 'Body', '/dashboard/channels', $owner);
        Notification::assertSentTo($owner, BusinessAlert::class, fn (BusinessAlert $n, array $via): bool => in_array('mail', $via, true));
    }

    public function test_optional_alerts_follow_chosen_roles_and_skip_the_actor(): void
    {
        [$merchant, $owner] = $this->merchantWithOwner();
        $manager = $this->staff($merchant, Role::MANAGER);
        $agent = $this->staff($merchant, Role::SALES_AGENT);
        $alerts = app(MerchantAlerts::class);
        $alerts->save($merchant, [AlertCatalog::SLIP_ISSUED => ['roles' => [Role::OWNER->value, Role::MANAGER->value, 'SuperAdmin'], 'email' => false]]);
        $this->assertSame([Role::OWNER->value, Role::MANAGER->value], $alerts->settings($merchant->refresh())[AlertCatalog::SLIP_ISSUED]['roles']);

        Notification::fake();
        $alerts->send($merchant, AlertCatalog::SLIP_ISSUED, 'Receipt issued', 'Body', null, $manager);
        Notification::assertSentTo($owner, BusinessAlert::class, fn (BusinessAlert $n, array $via): bool => $via === ['database']);
        Notification::assertNotSentTo($manager, BusinessAlert::class);
        Notification::assertNotSentTo($agent, BusinessAlert::class);
    }

    public function test_bell_lists_and_marks_read_and_settings_need_permission(): void
    {
        [$merchant, $owner] = $this->merchantWithOwner();
        $agent = $this->staff($merchant, Role::SALES_AGENT);
        app(MerchantAlerts::class)->send($merchant, AlertCatalog::CHANNEL_LINKED, 'WhatsApp linked', 'Someone linked WhatsApp.', '/dashboard/channels');

        $this->actingAs($owner);
        $res = $this->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('unread', 1)->assertJsonPath('data.0.title', 'WhatsApp linked');
        $id = $res->json('data.0.id');
        $this->postJson("/api/v1/notifications/{$id}/read")->assertOk();
        $this->getJson('/api/v1/notifications')->assertJsonPath('unread', 0);

        $this->getJson('/api/v1/settings/alerts')->assertOk()->assertJsonFragment(['event' => AlertCatalog::PAYOUT_CHANGED, 'mandatory' => true]);
        $this->putJson('/api/v1/settings/alerts', ['events' => [AlertCatalog::LOW_STOCK => ['roles' => [Role::OWNER->value], 'email' => false]]])->assertOk();
        $this->assertFalse(app(MerchantAlerts::class)->settings($merchant->refresh())[AlertCatalog::LOW_STOCK]['email']);

        // Another user's notification can't be touched; a Sales Agent can't change alert settings.
        $this->actingAs($agent);
        $this->postJson("/api/v1/notifications/{$id}/read")->assertNotFound();
        $this->putJson('/api/v1/settings/alerts', ['events' => []])->assertForbidden();
    }

    public function test_stock_tracking_and_oversell_blocking_are_separate_and_low_stock_alerts_once(): void
    {
        [$merchant, $owner] = $this->merchantWithOwner();
        $blocking = InventorySku::query()->create(['merchant_id' => $merchant->id, 'sku' => 'B-1', 'name' => 'Caps', 'unit_price_minor' => 1000, 'stock_quantity' => 6, 'low_stock_threshold' => 3]);
        $open = InventorySku::query()->create(['merchant_id' => $merchant->id, 'sku' => 'O-1', 'name' => 'Socks', 'unit_price_minor' => 500, 'stock_quantity' => 1, 'low_stock_threshold' => 0, 'block_overselling' => false]);
        $service = app(InventoryService::class);
        Notification::fake();

        $service->apply($merchant, [['quantity' => 3, 'sku_id' => $blocking->id]], -1);
        $service->apply($merchant, [['quantity' => 1, 'sku_id' => $blocking->id]], -1);
        Notification::assertSentToTimes($owner, BusinessAlert::class, 1);

        try {
            $service->apply($merchant, [['quantity' => 5, 'sku_id' => $blocking->id]], -1);
            $this->fail('Oversell should be blocked.');
        } catch (DomainRuleException $e) {
            $this->assertSame('insufficient_stock', $e->errorCode);
        }

        $service->apply($merchant, [['quantity' => 4, 'sku_id' => $open->id]], -1);
        $this->assertSame(-3, $open->refresh()->stock_quantity);
        $this->assertSame(2, $blocking->refresh()->stock_quantity);
    }
}
