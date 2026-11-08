<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Evidence that a person accepted a version of the Terms or Privacy Policy.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $merchant_id
 * @property string $document TERMS|PRIVACY
 * @property string $version
 * @property string $context SIGNUP|INVITATION|CHECKOUT
 * @property string|null $reference
 * @property string|null $ip
 * @property string|null $user_agent
 * @property CarbonImmutable $accepted_at
 * @property-read User|null $user
 */
final class LegalAcceptance extends Model
{
    public const string TERMS = 'TERMS';

    public const string PRIVACY = 'PRIVACY';

    public $timestamps = false;

    protected $fillable = ['user_id', 'merchant_id', 'document', 'version', 'context', 'reference', 'ip', 'user_agent', 'accepted_at'];

    protected function casts(): array
    {
        return ['accepted_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
