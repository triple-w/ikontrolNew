<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommercialRemissionItem extends Model
{
    protected $fillable = [
        'commercial_remission_id',
        'commercial_quote_item_id',
        'product_id',
        'sku',
        'snapshot_name',
        'snapshot_description',
        'snapshot_unit',
        'snapshot_unit_price',
        'quantity',
        'unit_price',
        'line_discount_amount',
        'line_subtotal',
        'line_base_before_global',
        'global_discount_share',
        'taxable_base',
        'tax_amount',
        'line_total',
        'sort_order',
        'notes',
    ];

    protected $casts = [
        'commercial_remission_id' => 'integer',
        'commercial_quote_item_id' => 'integer',
        'product_id' => 'integer',
        'snapshot_unit_price' => 'decimal:6',
        'quantity' => 'decimal:6',
        'unit_price' => 'decimal:6',
        'line_discount_amount' => 'decimal:6',
        'line_subtotal' => 'decimal:6',
        'line_base_before_global' => 'decimal:6',
        'global_discount_share' => 'decimal:6',
        'taxable_base' => 'decimal:6',
        'tax_amount' => 'decimal:6',
        'line_total' => 'decimal:6',
        'sort_order' => 'integer',
    ];

    public function remission(): BelongsTo
    {
        return $this->belongsTo(CommercialRemission::class, 'commercial_remission_id');
    }

    public function quoteItem(): BelongsTo
    {
        return $this->belongsTo(CommercialQuoteItem::class, 'commercial_quote_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'product_id');
    }

    public function taxes(): HasMany
    {
        return $this->hasMany(CommercialRemissionTax::class);
    }
}
