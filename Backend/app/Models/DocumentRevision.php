<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Forensic, append-only record of every change to an issued document.
 *
 * @property int $id
 * @property string $document_id
 * @property int $revision_number
 * @property int|null $actor_user_id
 * @property string $action
 * @property string|null $edit_reason
 * @property array<string, mixed> $before_snapshot_json
 * @property array<string, mixed> $after_snapshot_json
 * @property string|null $previous_verification_hash
 * @property string|null $actor_ip
 * @property string|null $user_agent
 * @property CarbonImmutable $created_at
 * @property-read User|null $actor
 */
class DocumentRevision extends Model
{
    public const string ACTION_EDIT = 'EDIT';

    public const string ACTION_VOID = 'VOID';

    public const string ACTION_CREDIT_NOTE = 'CREDIT_NOTE';

    public const string ACTION_PAYMENT = 'PAYMENT';

    public const null UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'document_id', 'revision_number', 'actor_user_id', 'action', 'edit_reason', 'before_snapshot_json',
        'after_snapshot_json', 'previous_verification_hash', 'actor_ip', 'user_agent',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'revision_number' => 'integer',
            'before_snapshot_json' => 'array',
            'after_snapshot_json' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * Field-level diff between the two snapshots (top-level keys only; items compared as a whole).
     *
     * @return array<string, array{before: mixed, after: mixed}>
     */
    public function diff(): array
    {
        $diff = [];
        $keys = array_unique([...array_keys($this->before_snapshot_json), ...array_keys($this->after_snapshot_json)]);
        foreach ($keys as $key) {
            $before = $this->before_snapshot_json[$key] ?? null;
            $after = $this->after_snapshot_json[$key] ?? null;
            if ($before !== $after) {
                $diff[$key] = ['before' => $before, 'after' => $after];
            }
        }

        return $diff;
    }
}
