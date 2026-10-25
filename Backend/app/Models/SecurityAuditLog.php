<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuditSeverity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only security audit trail (ISO 27001 A.8.15 logging).
 *
 * @property int $id
 * @property int|null $actor_user_id
 * @property string|null $merchant_id
 * @property string $event
 * @property AuditSeverity $severity
 * @property string|null $ip
 * @property string|null $user_agent
 * @property array<string, mixed>|null $context
 * @property CarbonImmutable $created_at
 * @property-read User|null $actor
 * @property-read Merchant|null $merchant
 */
class SecurityAuditLog extends Model
{
    public const null UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = ['actor_user_id', 'merchant_id', 'event', 'severity', 'ip', 'user_agent', 'context'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'severity' => AuditSeverity::class,
            'context' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /** @return BelongsTo<Merchant, $this> */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
