<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Channel;
use App\Models\Concerns\BelongsToMerchant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $merchant_id
 * @property Channel $channel
 * @property string $external_user_id
 * @property string|null $display_name
 * @property CarbonImmutable $linked_at
 * @property-read User $user
 */
class ChannelIdentity extends Model
{
    use BelongsToMerchant;

    /** @var list<string> */
    protected $fillable = ['user_id', 'merchant_id', 'channel', 'external_user_id', 'display_name', 'linked_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['channel' => Channel::class, 'linked_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
