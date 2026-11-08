<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\MerchantStatus;
use App\Enums\PlanTier;
use App\Models\Merchant;
use App\Services\Notifications\AlertCatalog;
use App\Services\Notifications\MerchantAlerts;
use App\Services\Settings\PlatformSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Plans are prepaid and never renew by themselves, so owners are reminded before (and just after)
 * their plan ends: in-app and by email, with a one-tap renew link.
 *
 * Timing is a list of day offsets: 7 = seven days before the end date, 0 = on the day, -3 = three
 * days after it lapsed. The Super-Admin sets the platform default and can override it for a single
 * business. Each reminder is claimed in plan_renewal_reminders first, so it goes once per paid term;
 * renewing moves the end date, which starts a fresh set.
 */
final class RenewalReminders
{
    public const string DEFAULT = '7,3,1,0,-3';

    public const int MAX_OFFSETS = 8;

    /** If the scheduler misses a day, a reminder may still go up to this many days late. */
    private const int CATCH_UP_DAYS = 2;

    public function __construct(
        private readonly PlatformSettings $settings,
        private readonly MerchantAlerts $alerts,
    ) {}

    /**
     * "14, 7,1,0,-3" → [14, 7, 1, 0, -3]. Null when it can't be read; [] for "off"/"none".
     *
     * @return list<int>|null
     */
    public static function parse(?string $text): ?array
    {
        $text = strtolower(trim((string) $text));
        if (in_array($text, ['off', 'none'], true)) {
            return [];
        }
        $out = [];
        foreach (preg_split('/[\s,;]+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            if (preg_match('/^[+-]?\d{1,2}$/', $part) !== 1) {
                return null;
            }
            $days = (int) $part;
            if ($days < -30 || $days > 60) {
                return null;
            }
            $out[$days] = $days;
        }
        if ($out === [] || count($out) > self::MAX_OFFSETS) {
            return null;
        }
        rsort($out);

        return $out;
    }

    /**
     * "7 days before · on the day · 3 days after" for people.
     *
     * @param  list<int>  $offsets
     */
    public static function describe(array $offsets): string
    {
        if ($offsets === []) {
            return 'No reminders';
        }

        return implode(' · ', array_map(static fn (int $d): string => match (true) {
            $d > 0 => $d.' '.($d === 1 ? 'day' : 'days').' before',
            $d === 0 => 'on the day',
            default => abs($d).' '.(abs($d) === 1 ? 'day' : 'days').' after',
        }, $offsets));
    }

    /** @return list<int> */
    public function platformOffsets(): array
    {
        return self::parse((string) $this->settings->get('billing.renewal_reminder_days')) ?? (self::parse(self::DEFAULT) ?? []);
    }

    /** @return list<int> the business's own timing when the Super-Admin set one, else the platform default */
    public function offsetsFor(Merchant $merchant): array
    {
        $own = $merchant->renewal_reminder_days !== null ? self::parse($merchant->renewal_reminder_days) : null;

        return $own ?? $this->platformOffsets();
    }

    /**
     * Which reminder is due when the plan ends in $days days (negative = already ended).
     *
     * @param  list<int>  $offsets
     */
    public static function stageFor(int $days, array $offsets): ?int
    {
        $due = null;
        foreach ($offsets as $offset) { // sorted high → low: the last match is the closest
            if ($days <= $offset && $offset - $days <= self::CATCH_UP_DAYS) {
                $due = $offset;
            }
        }

        return $due;
    }

    /** Sends today's reminders. Returns how many businesses were reminded. */
    public function run(?CarbonImmutable $today = null): int
    {
        $tz = (string) config('vendslip.timezone');
        $today ??= CarbonImmutable::now($tz)->startOfDay();
        $platform = $this->platformOffsets();
        $sent = 0;

        Merchant::query()
            ->where('plan_tier', '!=', PlanTier::FREE->value)
            ->where('status', MerchantStatus::ACTIVE->value)
            ->whereNotNull('plan_expires_at')
            ->whereBetween('plan_expires_at', [$today->subDays(31), $today->addDays(62)])
            ->orderBy('id')
            ->chunkById(200, function ($merchants) use ($today, $tz, $platform, &$sent): void {
                foreach ($merchants as $merchant) {
                    /** @var Merchant $merchant */
                    $offsets = $merchant->renewal_reminder_days !== null ? (self::parse($merchant->renewal_reminder_days) ?? $platform) : $platform;
                    $sent += $this->remind($merchant, $offsets, $today, $tz) ? 1 : 0;
                }
            });

        return $sent;
    }

    /** @param list<int> $offsets */
    private function remind(Merchant $merchant, array $offsets, CarbonImmutable $today, string $tz): bool
    {
        $ends = $merchant->plan_expires_at;
        if ($ends === null) {
            return false;
        }
        $days = (int) $today->diffInDays($ends->setTimezone($tz)->startOfDay(), false);
        $stage = self::stageFor($days, $offsets);
        if ($stage === null) {
            return false;
        }
        $claimed = DB::table('plan_renewal_reminders')->insertOrIgnore([
            'merchant_id' => $merchant->id, 'term_ends_at' => $ends, 'offset_days' => $stage, 'sent_at' => now(),
        ]);
        if ($claimed === 0) {
            return false;
        }

        $plan = $merchant->plan_tier->label();
        $date = $ends->setTimezone($tz)->format('j M Y');
        [$title, $body] = match (true) {
            $days > 1 => ["Your {$plan} plan ends in {$days} days", "It's paid until {$date} and doesn't renew by itself. Renew now to keep your paid features without a break; any days left are kept."],
            $days === 1 => ["Your {$plan} plan ends tomorrow", "It's paid until {$date}. Renew today to keep your paid features without a break."],
            $days === 0 => ["Your {$plan} plan ends today", 'Renew now to keep your paid features. After today the business moves to the Free plan; nothing is deleted.'],
            default => ["Your {$plan} plan has ended", "It ended on {$date}, so the business is on the Free plan. Your slips and settings are safe: renew to switch your paid features back on."],
        };

        try {
            $this->alerts->send($merchant, AlertCatalog::PLAN_RENEWAL, $title, $body, '/dashboard/billing?renew=1');
        } catch (Throwable $e) {
            DB::table('plan_renewal_reminders')->where('merchant_id', $merchant->id)->where('term_ends_at', $ends)->where('offset_days', $stage)->delete();
            Log::warning('renewal.send_failed', ['merchant_id' => $merchant->id, 'error' => $e->getMessage()]);

            return false;
        }

        return true;
    }
}
