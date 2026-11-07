<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $ticket_id
 * @property int|null $author_user_id
 * @property string $author_kind CUSTOMER|AGENT|SYSTEM
 * @property bool $is_internal
 * @property string $body
 * @property CarbonImmutable $created_at
 * @property-read SupportTicket $ticket
 * @property-read User|null $author
 */
final class SupportTicketMessage extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['ticket_id', 'author_user_id', 'author_kind', 'is_internal', 'body'];

    protected function casts(): array
    {
        return ['is_internal' => 'boolean', 'created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<SupportTicket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }
}
