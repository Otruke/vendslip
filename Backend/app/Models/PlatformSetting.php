<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $key
 * @property string|null $value Encrypted with the app key when is_secret = true.
 * @property bool $is_secret
 */
class PlatformSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = ['key', 'value', 'is_secret'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_secret' => 'boolean'];
    }
}
