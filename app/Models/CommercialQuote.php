<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommercialQuote extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_CONVERTED_TO_REMISSION = 'converted_to_remission';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SENT,
        self::STATUS_ACCEPTED,
        self::STATUS_REJECTED,
        self::STATUS_EXPIRED,
        self::STATUS_CANCELLED,
        self::STATUS_CONVERTED_TO_REMISSION,
    ];

    protected $fillable = [
        'users_id',
        'commercial_client_id',
        'commercial_contact_id',
        'fiscal_client_id',
        'commercial_document_template_id',
        'created_by_id',
        'assigned_user_id',
        'folio_prefix',
        'folio_number',
        'folio',
        'issued_at',
        'expires_at',
        'currency',
        'exchange_rate',
        'status',
        'commercial_terms',
        'internal_notes',
        'customer_notes',
        'global_discount_amount',
        'subtotal',
        'line_discount_total',
        'discount_total',
        'tax_total',
        'total',
        'template_name_snapshot',
        'logo_path_snapshot',
        'header_title_snapshot',
        'header_text_snapshot',
        'footer_text_snapshot',
        'terms_text_snapshot',
        'template_options_snapshot',
    ];

    protected $casts = [
        'users_id' => 'integer',
        'commercial_client_id' => 'integer',
        'commercial_contact_id' => 'integer',
        'fiscal_client_id' => 'integer',
        'commercial_document_template_id' => 'integer',
        'created_by_id' => 'integer',
        'assigned_user_id' => 'integer',
        'folio_number' => 'integer',
        'issued_at' => 'date',
        'expires_at' => 'date',
        'exchange_rate' => 'decimal:6',
        'global_discount_amount' => 'decimal:6',
        'subtotal' => 'decimal:6',
        'line_discount_total' => 'decimal:6',
        'discount_total' => 'decimal:6',
        'tax_total' => 'decimal:6',
        'total' => 'decimal:6',
        'template_options_snapshot' => 'array',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'users_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function commercialClient(): BelongsTo
    {
        return $this->belongsTo(CommercialClient::class);
    }

    public function commercialContact(): BelongsTo
    {
        return $this->belongsTo(CommercialContact::class);
    }

    public function fiscalClient(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'fiscal_client_id');
    }

    public function documentTemplate(): BelongsTo
    {
        return $this->belongsTo(CommercialDocumentTemplate::class, 'commercial_document_template_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CommercialQuoteItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function taxes(): HasMany
    {
        return $this->hasMany(CommercialQuoteTax::class)->orderBy('sort_order')->orderBy('id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(CommercialQuoteStatusHistory::class)->orderByDesc('changed_at')->orderByDesc('id');
    }

    public function canBeEdited(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SENT], true);
    }

    public function canBeDeleted(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('users_id', $userId);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($this->isAdmin($user)) {
            return $query;
        }

        return $query->where(function ($visible) use ($user) {
            $visible->where('users_id', $user->id)
                ->orWhere('created_by_id', $user->id)
                ->orWhere('assigned_user_id', $user->id);
        });
    }

    private function isAdmin(User $user): bool
    {
        $role = strtoupper((string) ($user->rol ?? ''));

        return (int) ($user->admin ?? 0) === 1 || str_contains($role, 'ADMIN');
    }
}
