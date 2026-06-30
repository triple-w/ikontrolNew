<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialQuoteTax extends Model
{
    public const TYPE_TRASLADO = 'traslado';
    public const TYPE_RETENCION = 'retencion';
    public const MODE_RATE = 'rate';
    public const MODE_ZERO = 'zero';
    public const MODE_EXEMPT = 'exempt';

    public const TYPES = [
        self::TYPE_TRASLADO,
        self::TYPE_RETENCION,
    ];

    public const MODES = [
        self::MODE_RATE,
        self::MODE_ZERO,
        self::MODE_EXEMPT,
    ];

    protected $fillable = [
        'commercial_quote_id',
        'commercial_quote_item_id',
        'tax_name',
        'tax_type',
        'tax_mode',
        'rate',
        'base',
        'amount',
        'sort_order',
    ];

    protected $casts = [
        'commercial_quote_id' => 'integer',
        'commercial_quote_item_id' => 'integer',
        'rate' => 'decimal:6',
        'base' => 'decimal:6',
        'amount' => 'decimal:6',
        'sort_order' => 'integer',
    ];

    public function quote(): BelongsTo
    {
        return $this->belongsTo(CommercialQuote::class, 'commercial_quote_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(CommercialQuoteItem::class, 'commercial_quote_item_id');
    }
}
