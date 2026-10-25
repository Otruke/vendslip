<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Channel;
use App\Enums\DraftSessionStatus;
use App\Models\Concerns\BelongsToMerchant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $merchant_id
 * @property int|null $actor_user_id
 * @property Channel $channel
 * @property string $external_chat_id
 * @property string $external_user_id
 * @property DraftSessionStatus $status
 * @property array<string, mixed> $draft_payload_json
 * @property string|null $ephemeral_message_id
 * @property string|null $confirmation_message_id
 * @property int $saved_outbound_calls_count
 * @property string|null $document_id
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read User|null $actor
 * @property-read Document|null $document
 */
class BotDraftSession extends Model
{
    use BelongsToMerchant, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'merchant_id', 'actor_user_id', 'channel', 'external_chat_id', 'external_user_id', 'status',
        'draft_payload_json', 'ephemeral_message_id', 'confirmation_message_id', 'saved_outbound_calls_count',
        'document_id', 'expires_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'channel' => Channel::class,
            'status' => DraftSessionStatus::class,
            'draft_payload_json' => 'array',
            'saved_outbound_calls_count' => 'integer',
            'expires_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [DraftSessionStatus::COLLECTING, DraftSessionStatus::AWAITING_CONFIRMATION], true)
            && $this->expires_at->isFuture();
    }
}
