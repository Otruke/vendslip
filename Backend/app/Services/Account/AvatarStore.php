<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Models\User;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Profile photos, stored privately.
 *
 *  - Files go on the private "local" disk (storage/app/private/avatars). Nothing under public/
 *    points at them, so there is no address anyone can type to fetch one.
 *  - The upload is never kept as sent: it is decoded, centre-cropped and re-encoded from raw
 *    pixels. That strips EXIF (including GPS location) and destroys anything hidden in the file
 *    (polyglot HTML/PHP payloads), and the stored name is random, never the client's file name.
 *  - Photos are only streamed by an authenticated route, to the owner, their own team, or
 *    platform staff (see canView()).
 */
final class AvatarStore
{
    public const int SIZE = 512;

    /** Refuse to decode anything bigger than this: guards against decompression bombs. */
    private const int MAX_SOURCE_PIXELS = 4000 * 4000;

    private const string DISK = 'local';

    /** @return list<string> Laravel validation rules for the upload field. */
    public static function rules(): array
    {
        return ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=96,min_height=96,max_width=4000,max_height=4000'];
    }

    public function store(User $user, UploadedFile $file, string $field = 'avatar'): void
    {
        $image = $this->decode($file, $field);
        $square = $this->squareCrop($image);
        [$bytes, $extension] = $this->encode($square);

        $path = 'avatars/'.$user->id.'/'.Str::lower(Str::random(40)).'.'.$extension;
        if (! Storage::disk(self::DISK)->put($path, $bytes)) {
            throw ValidationException::withMessages([$field => 'Could not save the photo. Please try again.']);
        }

        $old = $user->avatar_path;
        $user->forceFill(['avatar_path' => $path, 'avatar_updated_at' => now()])->save();
        if ($old !== null && $old !== $path) {
            Storage::disk(self::DISK)->delete($old);
        }
    }

    public function remove(User $user): void
    {
        if ($user->avatar_path !== null) {
            Storage::disk(self::DISK)->delete($user->avatar_path);
        }
        $user->forceFill(['avatar_path' => null, 'avatar_updated_at' => null])->save();
    }

    public function hasAvatar(User $user): bool
    {
        return $user->avatar_path !== null && Storage::disk(self::DISK)->exists($user->avatar_path);
    }

    /** The authenticated API address for a user's photo; the ?v= busts caches after a change. */
    public function apiUrl(User $user): ?string
    {
        if ($user->avatar_path === null) {
            return null;
        }

        return '/api/v1/users/'.$user->id.'/avatar?v='.($user->avatar_updated_at?->getTimestamp() ?? 0);
    }

    /** Who may see a photo: the person themself, anyone on the same business, or platform staff. */
    public function canView(User $viewer, User $owner): bool
    {
        if ($viewer->id === $owner->id || $viewer->isPlatformAdmin()) {
            return true;
        }

        return $viewer->merchant_id !== null && $viewer->merchant_id === $owner->merchant_id;
    }

    public function response(User $owner): Response
    {
        abort_unless($this->hasAvatar($owner), 404);
        $path = (string) $owner->avatar_path;
        $mime = str_ends_with($path, '.webp') ? 'image/webp' : 'image/jpeg';

        return response((string) Storage::disk(self::DISK)->get($path), 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="avatar"',
            // Private: browsers may keep it briefly, shared caches and proxies must not.
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ]);
    }

    private function decode(UploadedFile $file, string $field): GdImage
    {
        $fail = static fn (string $m): ValidationException => ValidationException::withMessages([$field => $m]);
        if (! function_exists('imagecreatefromstring')) {
            // Photos are always re-encoded; without PHP's GD extension we refuse rather than store the raw upload.
            throw $fail('Photo uploads are not available on this server yet (the PHP GD extension is off).');
        }
        $info = @getimagesize($file->getRealPath());
        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw $fail('Upload a JPG, PNG or WebP photo.');
        }
        if ($info[0] * $info[1] > self::MAX_SOURCE_PIXELS) {
            throw $fail('That photo is too large. Use one under 4000 × 4000 pixels.');
        }
        $image = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $image instanceof GdImage) {
            throw $fail('That photo could not be read. Try saving it again as JPG or PNG.');
        }
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $image = $this->applyExifRotation($image, $file->getRealPath());
        }

        return $image;
    }

    private function applyExifRotation(GdImage $image, string $path): GdImage
    {
        $exif = @exif_read_data($path);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };
        if ($angle === 0) {
            return $image;
        }
        $rotated = imagerotate($image, $angle, 0);

        return $rotated instanceof GdImage ? $rotated : $image;
    }

    private function squareCrop(GdImage $src): GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $side = min($w, $h);
        $out = imagecreatetruecolor(self::SIZE, self::SIZE);
        // Transparent PNGs land on white rather than black.
        imagefill($out, 0, 0, (int) imagecolorallocate($out, 255, 255, 255));
        imagecopyresampled($out, $src, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), self::SIZE, self::SIZE, $side, $side);

        return $out;
    }

    /** @return array{0: string, 1: string} encoded bytes and file extension */
    private function encode(GdImage $image): array
    {
        ob_start();
        if (function_exists('imagewebp') && (gd_info()['WebP Support'] ?? false)) {
            imagewebp($image, null, 82);
            $ext = 'webp';
        } else {
            imagejpeg($image, null, 85);
            $ext = 'jpg';
        }

        return [(string) ob_get_clean(), $ext];
    }
}
