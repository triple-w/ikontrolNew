<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialDocumentTemplate extends Model
{
    public const TYPE_QUOTE = 'quote';
    public const TYPE_REMISSION = 'remission';
    public const TYPE_GENERAL = 'general';

    public const TYPES = [
        self::TYPE_QUOTE,
        self::TYPE_REMISSION,
        self::TYPE_GENERAL,
    ];

    protected $fillable = [
        'users_id',
        'name',
        'document_type',
        'is_default',
        'logo_path',
        'header_title',
        'header_text',
        'footer_text',
        'terms_text',
        'accent_style',
        'show_logo',
        'show_contact_info',
        'show_fiscal_info',
        'show_item_tax',
        'show_item_sku',
        'show_notes',
        'is_active',
    ];

    protected $casts = [
        'users_id' => 'integer',
        'is_default' => 'boolean',
        'show_logo' => 'boolean',
        'show_contact_info' => 'boolean',
        'show_fiscal_info' => 'boolean',
        'show_item_tax' => 'boolean',
        'show_item_sku' => 'boolean',
        'show_notes' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'users_id');
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('users_id', $userId);
    }

    public function scopeForType(Builder $query, string $type): Builder
    {
        return $query->where('document_type', $type);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
