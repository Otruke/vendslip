<?php

declare(strict_types=1);

namespace App\Services\Bot;

use App\Enums\Channel;
use App\Models\ChannelIdentity;
use App\Models\ChannelLinkCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class IdentityLinker
{
    /** Short, unambiguous code (no 0/O/1/I). */
    public function issueCode(User $user): ChannelLinkCode
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (ChannelLinkCode::query()->where('code', $code)->exists());

        ChannelLinkCode::query()->where('user_id', $user->id)->whereNull('used_at')->delete();

        return ChannelLinkCode::query()->create([
            'user_id' => $user->id,
            'merchant_id' => (string) $user->merchant_id,
            'code' => $code,
            'expires_at' => now()->addMinutes((int) config('netslip.bots.link_code_ttl_minutes', 30)),
        ]);
    }

    public function resolve(Channel $channel, string $externalUserId): ?ChannelIdentity
    {
        return ChannelIdentity::query()->with('user.merchant')
            ->where('channel', $channel->value)->where('external_user_id', $externalUserId)->first();
    }

    public function link(Channel $channel, string $externalUserId, ?string $displayName, string $code): ?ChannelIdentity
    {
        $code = Str::upper((string) preg_replace('/[^A-Za-z0-9]/', '', $code));

        return DB::transaction(static function () use ($channel, $externalUserId, $displayName, $code): ?ChannelIdentity {
            $link = ChannelLinkCode::query()->where('code', $code)->whereNull('used_at')->where('expires_at', '>', now())->lockForUpdate()->first();
            if ($link === null || ! $link->user->is_active || $link->user->merchant_id !== $link->merchant_id) {
                return null;
            }
            $link->update(['used_at' => now()]);

            return ChannelIdentity::query()->updateOrCreate(
                ['channel' => $channel->value, 'external_user_id' => $externalUserId],
                ['user_id' => $link->user_id, 'merchant_id' => $link->merchant_id, 'display_name' => $displayName ? mb_substr($displayName, 0, 255) : null, 'linked_at' => now()],
            );
        });
    }
}
