<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlanTier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property string $engine
 * @property string|null $blade_view
 * @property string|null $html
 * @property bool $supports_receipt
 * @property bool $supports_invoice
 * @property PlanTier $min_plan_tier
 * @property array<string, string>|null $default_tokens_json
 * @property bool $is_active
 * @property bool $is_default
 * @property int $sort_order
 */
class Template extends Model
{
    public const string ENGINE_BLADE = 'blade';

    public const string ENGINE_HTML = 'html';

    public const string CLASSIC_EXECUTIVE = 'netslip-classic-executive';

    public const string MINIMALIST_SOCIAL = 'netslip-minimalist-social';

    /** Tokens available to admin-authored HTML templates. */
    public const array TOKENS = [
        'business_name', 'business_address', 'business_phone', 'business_email', 'logo_url', 'primary_color',
        'secondary_color', 'doc_title', 'doc_number', 'issued_at', 'due_date', 'status', 'customer_name',
        'customer_phone', 'customer_address', 'items_table', 'totals_block', 'bank_block', 'notes',
        'qr_verification', 'verification_code', 'watermark', 'tagline', 'footer',
    ];

    /** @var list<string> */
    protected $fillable = [
        'slug', 'name', 'description', 'engine', 'blade_view', 'html', 'supports_receipt', 'supports_invoice',
        'min_plan_tier', 'default_tokens_json', 'is_active', 'is_default', 'sort_order',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'supports_receipt' => 'boolean',
            'supports_invoice' => 'boolean',
            'min_plan_tier' => PlanTier::class,
            'default_tokens_json' => 'array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    public function isAvailableTo(PlanTier $tier): bool
    {
        return $this->is_active && $tier->atLeast($this->min_plan_tier);
    }
}
