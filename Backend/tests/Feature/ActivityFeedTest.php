<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Marketing\ActivityFeed;
use App\Services\Settings\PlatformSettings;
use Tests\TestCase;

/** Website activity notes: real events only, anonymous, opt-out respected, off by default. */
final class ActivityFeedTest extends TestCase
{
    public function test_off_by_default_then_only_real_anonymous_events(): void
    {
        $this->getJson('/api/v1/public/activity-feed')->assertOk()->assertExactJson(['enabled' => false]);

        $settings = app(PlatformSettings::class);
        $settings->set('marketing.popups.enabled', true);
        ActivityFeed::forget();
        $this->getJson('/api/v1/public/activity-feed')->assertOk()->assertJsonPath('enabled', true)->assertJsonPath('events', []);

        [$lagos, $owner] = $this->merchantWithOwner();
        $lagos->update(['name' => 'Amina Couture', 'city' => 'Ikeja', 'state' => 'Lagos', 'country_code' => 'NG']);
        [$hidden, $hiddenOwner] = $this->merchantWithOwner();
        $hidden->update(['city' => 'Accra', 'country_code' => 'GH', 'show_in_activity_feed' => false]);
        [, $nowhereOwner] = $this->merchantWithOwner(); // no location: never shown

        foreach ([$owner, $hiddenOwner, $nowhereOwner] as $user) {
            $this->actingAs($user)->postJson('/api/v1/documents', $this->receiptPayload())->assertCreated();
        }
        ActivityFeed::forget();

        $feed = $this->getJson('/api/v1/public/activity-feed')->assertOk();
        $places = collect($feed->json('events'))->pluck('place')->unique()->values()->all();
        $this->assertSame(['Ikeja, Lagos'], $places);
        $this->assertContains('RECEIPT', collect($feed->json('events'))->pluck('kind')->all());
        $this->assertContains('SIGNUP', collect($feed->json('events'))->pluck('kind')->all());
        $json = (string) json_encode($feed->json());
        $this->assertStringNotContainsString('Amina', $json);
        $this->assertStringNotContainsString('Ada Obi', $json);
        $this->assertSame(3, $feed->json('stats.slips_this_week'));
    }
}
