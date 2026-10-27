<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Role;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string|null $merchant_id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string $password
 * @property string|null $two_factor_secret
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property bool $is_active
 * @property CarbonImmutable|null $last_login_at
 * @property CarbonImmutable|null $email_verified_at
 * @property-read Merchant|null $merchant
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected string $guard_name = 'web';

    /** @var list<string> */
    protected $fillable = ['merchant_id', 'name', 'email', 'phone', 'password', 'is_active'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'phone' => null,
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
        'is_active' => true,
        'last_login_at' => null,
    ];

    /** @var list<string> */
    protected $hidden = ['password', 'remember_token', 'two_factor_secret'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'immutable_datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'immutable_datetime',
            'last_login_at' => 'immutable_datetime',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Merchant, $this> */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * Security events this user performed.
     *
     * @return HasMany<SecurityAuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(SecurityAuditLog::class, 'actor_user_id');
    }

    /** @return HasMany<ChannelIdentity, $this> */
    public function channelIdentities(): HasMany
    {
        return $this->hasMany(ChannelIdentity::class);
    }

    public function isPlatformAdmin(): bool
    {
        return $this->hasAnyRole([Role::SUPER_ADMIN->value, Role::ADMIN->value]);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Role::SUPER_ADMIN->value);
    }

    public function merchantRole(): ?Role
    {
        foreach (Role::merchantRoles() as $role) {
            if ($this->hasRole($role->value)) {
                return $role;
            }
        }

        return null;
    }

    /** Owners and managers are "privileged" for mutability rules; everyone else is staff. */
    public function isMerchantManager(): bool
    {
        return $this->hasAnyRole([Role::OWNER->value, Role::MANAGER->value]);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }
}
