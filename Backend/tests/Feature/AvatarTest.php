<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class AvatarTest extends TestCase
{
    /** A real photo-like image, in the given format, as raw bytes. */
    private function imageBytes(int $w = 800, int $h = 600, string $format = 'png'): string
    {
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, (int) imagecolorallocate($img, 55, 117, 129));
        imagefilledrectangle($img, 100, 100, 300, 300, (int) imagecolorallocate($img, 255, 255, 255));
        ob_start();
        $format === 'jpg' ? imagejpeg($img, null, 90) : imagepng($img);

        return (string) ob_get_clean();
    }

    private function upload(string $bytes, string $name = 'me.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $bytes);
    }

    public function test_upload_is_reencoded_and_kept_off_the_public_disk(): void
    {
        [, $owner] = $this->merchantWithOwner();

        $res = $this->actingAs($owner)->postJson('/api/v1/account/avatar', ['avatar' => $this->upload($this->imageBytes(), 'client-original-name.png')])->assertOk();
        $owner->refresh();

        $this->assertNotNull($owner->avatar_path);
        $this->assertStringStartsWith('avatars/'.$owner->id.'/', (string) $owner->avatar_path);
        $this->assertStringNotContainsString('client-original', basename((string) $owner->avatar_path), 'Client file names are never reused.');
        Storage::disk('local')->assertExists((string) $owner->avatar_path);
        $this->assertSame([], Storage::disk('public')->allFiles(), 'Nothing is written to the web-reachable disk.');

        $stored = (string) Storage::disk('local')->get((string) $owner->avatar_path);
        $size = getimagesizefromstring($stored);
        $this->assertNotFalse($size);
        $this->assertSame([512, 512], [$size[0], $size[1]], 'Centre-cropped to a square.');
        $this->assertSame('/api/v1/users/'.$owner->id.'/avatar?v='.$owner->avatar_updated_at?->getTimestamp(), $res->json('avatar_url'));
        $this->getJson('/api/v1/auth/me')->assertJsonPath('user.avatar_url', $res->json('avatar_url'));
        $this->assertArrayNotHasKey('avatar_path', $owner->toArray(), 'The storage path is never serialised.');
    }

    public function test_hidden_payloads_and_metadata_do_not_survive(): void
    {
        [, $owner] = $this->merchantWithOwner();

        // A PNG with a PHP/HTML payload glued on the end (a classic polyglot).
        $polyglot = $this->imageBytes().'<?php system($_GET["c"]); ?><script>alert(1)</script>';
        $this->actingAs($owner)->postJson('/api/v1/account/avatar', ['avatar' => $this->upload($polyglot)])->assertOk();
        $stored = (string) Storage::disk('local')->get((string) $owner->fresh()?->avatar_path);
        $this->assertStringNotContainsString('<?php', $stored);
        $this->assertStringNotContainsString('<script', $stored);

        // A JPEG carrying an EXIF block (where phones keep GPS location).
        $jpeg = $this->imageBytes(640, 640, 'jpg');
        $exif = "Exif\0\0SECRET-GPS-48.8584N-2.2945E";
        $withExif = substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2);
        $this->postJson('/api/v1/account/avatar', ['avatar' => $this->upload($withExif, 'phone.jpg')])->assertOk();
        $stored = (string) Storage::disk('local')->get((string) $owner->fresh()?->avatar_path);
        $this->assertStringNotContainsString('SECRET-GPS', $stored);
    }

    public function test_bad_uploads_are_rejected(): void
    {
        [, $owner] = $this->merchantWithOwner();
        $this->actingAs($owner);

        $this->postJson('/api/v1/account/avatar', ['avatar' => UploadedFile::fake()->createWithContent('x.png', 'not an image')])->assertUnprocessable()->assertJsonValidationErrors('avatar');
        $this->postJson('/api/v1/account/avatar', ['avatar' => UploadedFile::fake()->create('cv.pdf', 20, 'application/pdf')])->assertUnprocessable();
        $this->postJson('/api/v1/account/avatar', ['avatar' => $this->upload($this->imageBytes(40, 40))])->assertUnprocessable();
        $this->postJson('/api/v1/account/avatar', ['avatar' => UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')])->assertUnprocessable();
        $this->assertNull($owner->fresh()?->avatar_path);
    }

    public function test_only_the_owner_their_team_and_platform_staff_can_view(): void
    {
        [$merchant, $owner] = $this->merchantWithOwner();
        $this->actingAs($owner)->postJson('/api/v1/account/avatar', ['avatar' => $this->upload($this->imageBytes())])->assertOk();
        $url = '/api/v1/users/'.$owner->id.'/avatar';

        $res = $this->get($url)->assertOk();
        $this->assertStringStartsWith('image/', (string) $res->headers->get('Content-Type'));
        $this->assertSame('nosniff', $res->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('private', (string) $res->headers->get('Cache-Control'));

        $teammate = User::factory()->create(['merchant_id' => $merchant->id]);
        $teammate->assignRole(Role::SALES_AGENT->value);
        $this->actingAs($teammate)->get($url)->assertOk();

        [, $stranger] = $this->merchantWithOwner();
        $this->actingAs($stranger)->get($url)->assertNotFound();

        $admin = User::factory()->create();
        $admin->assignRole(Role::ADMIN->value);
        $this->actingAs($admin)->get(route('admin.avatar', $owner))->assertOk();
    }

    public function test_guests_get_nothing_and_there_is_no_public_address(): void
    {
        [, $owner] = $this->merchantWithOwner();
        $this->actingAs($owner)->postJson('/api/v1/account/avatar', ['avatar' => $this->upload($this->imageBytes())])->assertOk();
        $path = (string) $owner->fresh()?->avatar_path;
        auth()->forgetGuards();
        $this->app['auth']->guard('web')->logout();

        $this->getJson('/api/v1/users/'.$owner->id.'/avatar')->assertUnauthorized();
        $this->get(route('admin.avatar', $owner))->assertRedirect();
        // Laravel's private-disk route only answers correctly signed, expiring URLs, and we never sign one.
        $this->assertContains($this->get('/storage/'.$path)->status(), [403, 404]);
        $this->get('/'.$path)->assertNotFound();
    }

    public function test_replacing_and_removing_cleans_up_files(): void
    {
        [, $owner] = $this->merchantWithOwner();
        $this->actingAs($owner)->postJson('/api/v1/account/avatar', ['avatar' => $this->upload($this->imageBytes())])->assertOk();
        $first = (string) $owner->fresh()?->avatar_path;
        $this->postJson('/api/v1/account/avatar', ['avatar' => $this->upload($this->imageBytes(700, 900))])->assertOk();
        Storage::disk('local')->assertMissing($first);

        $this->deleteJson('/api/v1/account/avatar')->assertOk();
        $this->assertNull($owner->fresh()?->avatar_path);
        $this->assertSame([], Storage::disk('local')->allFiles('avatars/'.$owner->id));
        $this->getJson('/api/v1/users/'.$owner->id.'/avatar')->assertNotFound();
    }

    public function test_admins_upload_their_own_photo_from_the_portal(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::SUPER_ADMIN->value);

        $this->actingAs($admin)->from(route('admin.profile.show'))
            ->post(route('admin.profile.avatar'), ['avatar' => $this->upload($this->imageBytes())])
            ->assertRedirect(route('admin.profile.show'))->assertSessionHas('status');
        $this->assertNotNull($admin->fresh()?->avatar_path);
        $this->get(route('admin.profile.show'))->assertOk()->assertSee(route('admin.avatar', $admin), false);
        $this->get(route('admin.avatar', $admin))->assertOk();

        $this->delete(route('admin.profile.avatar.delete'))->assertSessionHas('status');
        $this->assertNull($admin->fresh()?->avatar_path);
    }
}
