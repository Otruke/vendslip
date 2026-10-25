<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\Channel;
use App\Enums\DraftSessionStatus;
use App\Enums\Permission;
use App\Models\BotDraftSession;
use App\Models\ChannelIdentity;
use App\Models\Document;
use App\Services\Bot\IdentityLinker;
use App\Services\Settings\PlatformSettings;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ChannelController extends ApiController
{
    /** Platform setting that must be present for each bot channel to be live. */
    private const array SETTING_KEYS = [
        'WHATSAPP' => 'twilio.whatsapp_from',
        'TELEGRAM' => 'telegram.bot_token',
        'INSTAGRAM' => 'twilio.instagram_from',
        'FACEBOOK' => 'twilio.facebook_from',
    ];

    public function __construct(
        private readonly IdentityLinker $linker,
        private readonly PlatformSettings $settings,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $merchant = $this->merchant($request);
        $user = $this->user($request);
        $since = now()->subDays(30);

        $identities = ChannelIdentity::query()->forMerchant($merchant)->with('user')->orderByDesc('linked_at')->get();
        $sessions = BotDraftSession::query()->forMerchant($merchant)->where('created_at', '>=', $since);

        $whatsappFrom = $this->settings->string('twilio.whatsapp_from');
        $telegramUser = $this->settings->string('telegram.bot_username');

        return response()->json([
            'channels' => array_map(function (Channel $channel) use ($merchant, $since): array {
                $count = Document::query()->forMerchant($merchant)->where('channel', $channel->value)->where('issued_at', '>=', $since)->count();

                return [
                    'channel' => $channel->value,
                    'label' => $channel->label(),
                    'configured' => $this->settings->isConfigured(self::SETTING_KEYS[$channel->value] ?? 'telegram.bot_token'),
                    'slips_30d' => $count,
                ];
            }, array_filter(Channel::cases(), static fn (Channel $c): bool => $c !== Channel::WEB)),
            'identities' => $identities->map(static fn (ChannelIdentity $i): array => [
                'id' => $i->id,
                'channel' => $i->channel->value,
                'display_name' => $i->display_name,
                'external_user_id' => $i->channel === Channel::TELEGRAM ? $i->external_user_id : preg_replace('/(\d{3})\d+(\d{3})/', '$1••••$2', $i->external_user_id),
                'user_name' => $i->user->name,
                'is_mine' => $i->user_id === $user->id,
                'linked_at' => $i->linked_at->toIso8601String(),
            ])->values(),
            'stats' => [
                'drafts_30d' => (clone $sessions)->count(),
                'confirmed_30d' => (clone $sessions)->where('status', DraftSessionStatus::CONFIRMED->value)->count(),
                'api_calls_saved_30d' => (int) (clone $sessions)->sum('saved_outbound_calls_count'),
            ],
            'handles' => [
                'whatsapp_number' => $whatsappFrom !== null ? '+'.Phone::internationalDigits($whatsappFrom) : null,
                'whatsapp_link' => $whatsappFrom !== null ? 'https://wa.me/'.Phone::internationalDigits($whatsappFrom) : null,
                'telegram_username' => $telegramUser,
                'telegram_link' => $telegramUser !== null ? 'https://t.me/'.ltrim($telegramUser, '@') : null,
            ],
        ]);
    }

    public function linkCode(Request $request): JsonResponse
    {
        $this->authorizePermission($request, Permission::DOCUMENTS_ISSUE);
        $code = $this->linker->issueCode($this->user($request));
        $telegramUser = $this->settings->string('telegram.bot_username');
        $whatsappFrom = $this->settings->string('twilio.whatsapp_from');

        return response()->json([
            'code' => $code->code,
            'expires_at' => $code->expires_at->toIso8601String(),
            'telegram_deep_link' => $telegramUser !== null ? 'https://t.me/'.ltrim($telegramUser, '@').'?start='.$code->code : null,
            'whatsapp_deep_link' => $whatsappFrom !== null ? 'https://wa.me/'.Phone::internationalDigits($whatsappFrom).'?text='.rawurlencode('/link '.$code->code) : null,
        ], 201);
    }

    public function unlink(Request $request, int $id): JsonResponse
    {
        $user = $this->user($request);
        $identity = ChannelIdentity::query()->forMerchant($this->merchant($request))->findOrFail($id);
        abort_unless($identity->user_id === $user->id || $user->can(Permission::CHANNELS_MANAGE->value), 403);
        $identity->delete();

        return response()->json(['ok' => true]);
    }
}
