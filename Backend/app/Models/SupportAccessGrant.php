<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $merchant_id
 * @property int $admin_user_id
 * @property int|null $target_user_id
 * @property list<string> $recipients
 * @property string $otp_hash
 * @property CarbonImmutable $otp_expires_at
 * @property int $attempts
 * @property CarbonImmutable|null $verified_at
 * @property string|null $handoff_token_hash
 * @property CarbonImmutable|null $handoff_expires_at
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $ended_at
 * @property string|null $end_reason
 * @property CarbonImmutable $created_at
 * @property-read Merchant $merchant
 * @property-read User $admin
 * @property-read User|null $target
 */
final class SupportAccessGrant extends Model
{
    protected $fillable = [
        'merchant_id', 'admin_user_id', 'target_user_id', 'recipients', 'otp_hash', 'otp_expires_at', 'attempts',
        'verified_at', 'handoff_token_hash', 'handoff_expires_at', 'started_at', 'expires_at', 'ended_at', 'end_reason',
    ];

    protected $hidden = ['otp_hash', 'handoff_token_hash'];

    protected function casts(): array
    {
        return [
            'recipients' => 'array',
            'attempts' => 'integer',
            'otp_expires_at' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime',
            'handoff_expires_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Merchant, $this> */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /** @return BelongsTo<User, $this> */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /** A code was sent and can still be entered. */
    public function isAwaitingCode(): bool
    {
        return $this->verified_at === null && $this->ended_at === null && $this->otp_expires_at->isFuture();
    }

    /** The support session is running right now. */
    public function isLive(): bool
    {
        return $this->started_at !== null && $this->ended_at === null && $this->expires_at !== null && $this->expires_at->isFuture();
    }
}
