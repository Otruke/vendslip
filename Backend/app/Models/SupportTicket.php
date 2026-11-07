<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $reference
 * @property string $merchant_id
 * @property int|null $opened_by_user_id
 * @property string $subject
 * @property string $category
 * @property string $priority
 * @property string $status
 * @property int|null $assigned_to_user_id
 * @property CarbonImmutable $last_activity_at
 * @property CarbonImmutable|null $first_response_at
 * @property CarbonImmutable|null $resolved_at
 * @property int|null $qa_score
 * @property int|null $qa_reviewed_by_user_id
 * @property CarbonImmutable|null $qa_reviewed_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Merchant $merchant
 * @property-read User|null $openedBy
 * @property-read User|null $assignee
 * @property-read User|null $qaReviewer
 * @property-read Collection<int, SupportTicketMessage> $messages
 */
final class SupportTicket extends Model
{
    public const array CATEGORIES = ['BILLING' => 'Billing & plans', 'TECHNICAL' => 'Something isn’t working', 'BOT' => 'WhatsApp, Telegram & chats', 'ACCOUNT' => 'Account & team', 'OTHER' => 'Something else'];

    public const array PRIORITIES = ['LOW' => 'Low', 'NORMAL' => 'Normal', 'HIGH' => 'High', 'URGENT' => 'Urgent'];

    /** OPEN: waiting for support · AWAITING_CUSTOMER: support replied · RESOLVED / CLOSED. */
    public const array STATUSES = ['OPEN' => 'Waiting for support', 'AWAITING_CUSTOMER' => 'Waiting for you', 'RESOLVED' => 'Resolved', 'CLOSED' => 'Closed'];

    protected $fillable = [
        'reference', 'merchant_id', 'opened_by_user_id', 'subject', 'category', 'priority', 'status', 'assigned_to_user_id',
        'last_activity_at', 'first_response_at', 'resolved_at', 'qa_score', 'qa_reviewed_by_user_id', 'qa_reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'last_activity_at' => 'immutable_datetime',
            'first_response_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
            'qa_reviewed_at' => 'immutable_datetime',
            'qa_score' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['RESOLVED', 'CLOSED'], true);
    }

    /** @return BelongsTo<Merchant, $this> */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /** @return BelongsTo<User, $this> */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function qaReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'qa_reviewed_by_user_id');
    }

    /** @return HasMany<SupportTicketMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class, 'ticket_id')->orderBy('id');
    }
}
