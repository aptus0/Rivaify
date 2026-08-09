<?php

namespace Modules\Commerce\Models\Checkout;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Commerce\Enums\Checkout\CheckoutFieldRequirement;

/**
 * The single mutable draft row a merchant edits (one per store — see the
 * migration's unique constraint). Publishing snapshots the current state
 * into a new CheckoutSettingVersion and bumps current_version; this row
 * itself is never what checkout.rivaify.com renders directly (it renders
 * the latest published CheckoutSettingVersion), so editing here is always
 * safe to preview without affecting live checkout.
 */
#[Fillable([
    'logo_media_id', 'layout', 'primary_color', 'background_color', 'text_color',
    'heading_font', 'body_font', 'button_radius', 'input_radius',
    'phone_requirement', 'company_requirement', 'tax_number_requirement',
    'order_note_enabled', 'marketing_consent_enabled', 'policy_links', 'current_version',
])]
class CheckoutSetting extends Model
{
    use BelongsToStore, HasFactory, HasUlid;

    protected $attributes = [
        'layout' => 'modern',
        'primary_color' => '#111111',
        'background_color' => '#FFFFFF',
        'text_color' => '#111111',
        'heading_font' => 'Manrope',
        'body_font' => 'Inter',
        'button_radius' => 8,
        'input_radius' => 8,
        'phone_requirement' => 'required',
        'company_requirement' => 'hidden',
        'tax_number_requirement' => 'hidden',
        'order_note_enabled' => false,
        'marketing_consent_enabled' => true,
        'current_version' => 0,
    ];

    protected function casts(): array
    {
        return [
            'phone_requirement' => CheckoutFieldRequirement::class,
            'company_requirement' => CheckoutFieldRequirement::class,
            'tax_number_requirement' => CheckoutFieldRequirement::class,
            'button_radius' => 'integer',
            'input_radius' => 'integer',
            'order_note_enabled' => 'boolean',
            'marketing_consent_enabled' => 'boolean',
            'policy_links' => 'array',
            'current_version' => 'integer',
        ];
    }

    // logo_media_id intentionally has no Eloquent relation yet — it FKs to
    // theme_assets, which has a migration but no model class in this
    // codebase yet (storefront builder work still in progress elsewhere).
    // Add a logo() belongsTo() here once that model exists.

    public function versions(): HasMany
    {
        return $this->hasMany(CheckoutSettingVersion::class);
    }
}
