<?php

declare(strict_types=1);

namespace App\Services\Marketing;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Models\Document;
use App\Models\Merchant;
use App\Services\Settings\PlatformSettings;
use App\Support\Countries;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The website's live activity notes ("A business in Ikeja, Lagos just issued a receipt").
 *
 * Only real events from the last two days, only from businesses that haven't opted out and have a
 * location, and never a business name, customer or amount. Stats are true counts. When there's
 * nothing real to show, nothing is shown: events are never invented.
 */
final class ActivityFeed
{
    private const int MAX_EVENTS = 12;

    public function __construct(private readonly PlatformSettings $settings) {}

    /** @return array<string, mixed> */
    public function payload(): array
    {
        if (! (bool) $this->settings->get('marketing.popups.enabled')) {
            return ['enabled' => false];
        }
        $min = max(5, min(600, (int) $this->settings->get('marketing.popups.min_seconds')));
        $max = max($min, min(900, (int) $this->settings->get('marketing.popups.max_seconds')));

        return [
            'enabled' => true,
            'min_seconds' => $min,
            'max_seconds' => $max,
            'max_per_visit' => max(1, min(20, (int) $this->settings->get('marketing.popups.max_per_visit'))),
            ...Cache::remember('marketing.activity_feed.v1', 300, fn (): array => [
                'events' => $this->events(),
                'stats' => (bool) $this->settings->get('marketing.popups.show_stats') ? $this->stats() : null,
            ]),
        ];
    }

    public static function forget(): void
    {
        Cache::forget('marketing.activity_feed.v1');
    }

    /** @return list<array{kind: string, place: string, minutes_ago: int, plan?: string}> */
    private function events(): array
    {
        $since = now()->subHours(48);
        $located = static fn ($q) => $q->whereNotNull('merchants.city')->orWhereNotNull('merchants.country_code');
        $rows = [];

        $slips = DB::table('documents')
            ->join('merchants', 'merchants.id', '=', 'documents.merchant_id')
            ->where('merchants.show_in_activity_feed', true)->where('merchants.status', 'ACTIVE')->where($located)
            ->whereIn('documents.doc_type', [DocumentType::RECEIPT->value, DocumentType::INVOICE->value])
            ->whereNull('documents.parent_document_id')
            ->where('documents.status', '!=', DocumentStatus::VOID->value)
            ->where('documents.issued_at', '>=', $since)
            ->orderByDesc('documents.issued_at')->limit(60)
            ->get(['documents.merchant_id', 'documents.doc_type', 'documents.issued_at as at', 'merchants.city', 'merchants.state', 'merchants.country_code'])
            ->unique('merchant_id'); // one note per business, its latest slip
        foreach ($slips as $r) {
            $rows[] = self::row($r->doc_type === DocumentType::INVOICE->value ? 'INVOICE' : 'RECEIPT', $r);
        }

        $signups = DB::table('merchants')->where('show_in_activity_feed', true)->where('status', 'ACTIVE')->where($located)
            ->where('created_at', '>=', $since)->orderByDesc('created_at')->limit(20)
            ->get(['created_at as at', 'city', 'state', 'country_code']);
        foreach ($signups as $r) {
            $rows[] = self::row('SIGNUP', $r);
        }

        $upgrades = DB::table('payment_transactions')
            ->join('merchants', 'merchants.id', '=', 'payment_transactions.merchant_id')
            ->where('merchants.show_in_activity_feed', true)->where($located)
            ->where('payment_transactions.purpose', PaymentPurpose::SUBSCRIPTION->value)
            ->where('payment_transactions.status', PaymentStatus::SUCCESSFUL->value)
            ->where('payment_transactions.paid_at', '>=', $since)
            ->orderByDesc('payment_transactions.paid_at')->limit(20)
            ->get(['payment_transactions.plan_tier', 'payment_transactions.paid_at as at', 'merchants.city', 'merchants.state', 'merchants.country_code']);
        foreach ($upgrades as $r) {
            $rows[] = self::row('UPGRADE', $r, $r->plan_tier === 'BUSINESS_PLUS' ? 'Business Plus' : 'Pro');
        }

        $rows = array_values(array_filter($rows, static fn (array $r): bool => $r['place'] !== ''));
        usort($rows, static fn (array $a, array $b): int => $b['at'] <=> $a['at']);

        $out = [];
        foreach (array_slice($rows, 0, self::MAX_EVENTS) as $r) {
            $event = ['kind' => $r['kind'], 'place' => $r['place'], 'minutes_ago' => max(1, (int) $r['at']->diffInMinutes(now()))];
            if ($r['plan'] !== null) {
                $event['plan'] = $r['plan'];
            }
            $out[] = $event;
        }

        return $out;
    }

    /** @return array{kind: string, at: CarbonImmutable, place: string, plan: string|null} */
    private static function row(string $kind, object $r, ?string $plan = null): array
    {
        return [
            'kind' => $kind,
            'at' => CarbonImmutable::parse((string) ($r->at ?? 'now')),
            'place' => self::place(self::str($r->city ?? null), self::str($r->state ?? null), self::str($r->country_code ?? null)),
            'plan' => $plan,
        ];
    }

    private static function str(mixed $v): ?string
    {
        return is_string($v) && trim($v) !== '' ? trim($v) : null;
    }

    /** @return array{slips_this_week: int, businesses: int} */
    private function stats(): array
    {
        return [
            'slips_this_week' => Document::query()->whereIn('doc_type', [DocumentType::RECEIPT->value, DocumentType::INVOICE->value])
                ->where('status', '!=', DocumentStatus::VOID->value)->where('issued_at', '>=', now()->subDays(7))->count(),
            'businesses' => Merchant::query()->where('status', 'ACTIVE')->count(),
        ];
    }

    /** "Ikeja, Lagos" · "Accra, Ghana" · "Kenya" — never more precise than a city. */
    private static function place(?string $city, ?string $state, ?string $country): string
    {
        $countryName = $country !== null ? Countries::name($country) : null;
        $city = $city !== null && trim($city) !== '' ? trim($city) : null;
        $state = $state !== null && trim($state) !== '' ? trim($state) : null;
        $parts = $city !== null
            ? [$city, $state !== null && strcasecmp($state, $city) !== 0 ? $state : $countryName]
            : [$state !== null && $countryName !== null ? $state : null, $countryName];

        return implode(', ', array_filter($parts, static fn (?string $p): bool => $p !== null && $p !== ''));
    }
}
