<?php

declare(strict_types=1);

namespace App\Services\Legal;

use App\Models\LegalAcceptance;
use App\Models\User;
use App\Services\Settings\PlatformSettings;
use Illuminate\Http\Request;

/**
 * Records acceptance of the Terms of Service and Privacy Policy: which version, who, when, from
 * which IP/device and in what context (sign-up, joining a team, an update, or before a payment). Each
 * record is kept as evidence of consent and contract acceptance (GDPR Art. 7(1), NDPA 2023 s.26).
 *
 * Versions are dates (YYYY-MM-DD) = the day that version takes effect, so they sort as text.
 * The first version comes from config (VENDSLIP_LEGAL_VERSION). A Super-Admin publishes later ones
 * from the admin console with a short "what changed" summary and an effective date:
 *  - from publishing until that date, people are told about it and may accept early;
 *  - from that date, anyone who hasn't accepted it must do so before they can carry on.
 */
final class LegalConsent
{
    public const string SETTING = 'legal.versions';

    public const string SIGNUP = 'SIGNUP';

    public const string INVITATION = 'INVITATION';

    public const string CHECKOUT = 'CHECKOUT';

    public const string UPDATE = 'UPDATE';

    public function __construct(private readonly PlatformSettings $settings) {}

    /** The newest published version: what the pages show and what people accept from now on. */
    public static function version(): string
    {
        return app(self::class)->latest()['version'];
    }

    /**
     * Published versions, newest first. The config version is the base and always listed last.
     *
     * @return list<array{version: string, summary: string|null, published_at: string|null, published_by: string|null}>
     */
    public function history(): array
    {
        $base = (string) config('vendslip.legal.version');
        $raw = json_decode((string) $this->settings->get(self::SETTING, '[]'), true);
        $rows = [];
        foreach (is_array($raw) ? $raw : [] as $row) {
            if (is_array($row) && is_string($row['version'] ?? null) && strcmp($row['version'], $base) > 0) {
                $rows[$row['version']] = [
                    'version' => $row['version'],
                    'summary' => is_string($row['summary'] ?? null) ? $row['summary'] : null,
                    'published_at' => is_string($row['published_at'] ?? null) ? $row['published_at'] : null,
                    'published_by' => is_string($row['published_by'] ?? null) ? $row['published_by'] : null,
                ];
            }
        }
        krsort($rows, SORT_STRING);
        $rows[$base] = ['version' => $base, 'summary' => null, 'published_at' => null, 'published_by' => null];

        return array_values($rows);
    }

    /** @return array{version: string, summary: string|null, published_at: string|null, published_by: string|null, in_force: bool} */
    public function latest(): array
    {
        $latest = $this->history()[0];

        return [...$latest, 'in_force' => strcmp($latest['version'], self::today()) <= 0];
    }

    /** The newest version that has already taken effect. */
    public function inForce(): string
    {
        foreach ($this->history() as $row) {
            if (strcmp($row['version'], self::today()) <= 0) {
                return $row['version'];
            }
        }

        return (string) config('vendslip.legal.version');
    }

    public function publish(string $version, string $summary, User $by): void
    {
        $rows = array_filter($this->history(), fn (array $r): bool => $r['published_at'] !== null && $r['version'] !== $version);
        $rows[] = ['version' => $version, 'summary' => $summary, 'published_at' => now()->toIso8601String(), 'published_by' => $by->name];
        $this->settings->set(self::SETTING, (string) json_encode(array_values($rows)));
    }

    /** The newest version this person has accepted, or null if none is on record. */
    public function acceptedVersion(User $user): ?string
    {
        $version = LegalAcceptance::query()->where('user_id', $user->id)->where('document', LegalAcceptance::TERMS)->max('version');

        return is_string($version) ? $version : null;
    }

    public function hasAcceptedCurrent(User $user): bool
    {
        return strcmp((string) $this->acceptedVersion($user), self::version()) >= 0;
    }

    /** True once the version they haven't accepted has taken effect: they must accept before carrying on. */
    public function mustAccept(User $user): bool
    {
        return strcmp((string) $this->acceptedVersion($user), $this->inForce()) < 0;
    }

    /**
     * What the web app needs to show a notice or the accept screen.
     *
     * @return array{version: string, effective_on: string, summary: string|null, accepted_version: string|null, up_to_date: bool, required: bool}
     */
    public function status(User $user): array
    {
        $latest = $this->latest();
        $accepted = $this->acceptedVersion($user);

        return [
            'version' => $latest['version'],
            'effective_on' => $latest['version'],
            'summary' => $latest['summary'],
            'accepted_version' => $accepted,
            'up_to_date' => strcmp((string) $accepted, $latest['version']) >= 0,
            'required' => $this->mustAccept($user),
        ];
    }

    /** Records both documents at the newest version. */
    public function record(User $user, string $context, Request $request, ?string $reference = null): void
    {
        $now = now();
        $version = self::version();
        foreach ([LegalAcceptance::TERMS, LegalAcceptance::PRIVACY] as $document) {
            LegalAcceptance::query()->create([
                'user_id' => $user->id,
                'merchant_id' => $user->merchant_id,
                'document' => $document,
                'version' => $version,
                'context' => $context,
                'reference' => $reference,
                'ip' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500) ?: null,
                'accepted_at' => $now,
            ]);
        }
    }

    private static function today(): string
    {
        return now(config('vendslip.timezone'))->toDateString();
    }
}
