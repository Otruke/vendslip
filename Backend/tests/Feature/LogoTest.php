<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PlanTier;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Logos are served by the app with a versioned URL, so a new upload shows at once. */
final class LogoTest extends TestCase
{
    public function test_uploaded_logo_is_served_and_url_changes_on_replace(): void
    {
        [, $owner] = $this->merchantWithOwner(PlanTier::PRO);
        $this->actingAs($owner);

        $first = $this->post('/api/v1/settings/logo', ['logo' => UploadedFile::fake()->image('logo.png', 200, 80)], ['Accept' => 'application/json'])
            ->assertOk()->json('data.logo_url');
        $this->assertStringStartsWith('/api/v1/public/merchants/', $first);
        $this->get($first)->assertOk()->assertHeader('Content-Type', 'image/png');

        $second = $this->post('/api/v1/settings/logo', ['logo' => UploadedFile::fake()->image('new.png', 200, 80)], ['Accept' => 'application/json'])
            ->assertOk()->json('data.logo_url');
        $this->assertNotSame($first, $second);
        $this->get($second)->assertOk();

        [$free] = $this->merchantWithOwner();
        $this->get('/api/v1/public/merchants/'.$free->id.'/logo')->assertNotFound();
    }
}
