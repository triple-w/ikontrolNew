<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialRemissionTax extends Model
{
    public const TYPE_TRASLADO = 'traslado';
    public const TYPE_RETENCION = 'retencion';
    public const MODE_RATE = 'rate';
    public const MODE_ZERO = 'zero';
    public const MODE_EXEMPT = 'exempt';

    protected $fillable = [
        'commercial_remission_id',
        'commercial_remission_item_id',
        'tax_name',
        'tax_type',
        'tax_mode',
        'rate',
        'base',
        'amount',
        'sort_order',
    ];

    protected $casts = [
        'commercial_remission_id' => 'integer',
        'commercial_remission_item_id' => 'integer',
        'rate' => 'decimal:6',
        'base' => 'decimal:6',
        'amount' => 'decimal:6',
        'sort_order' => 'integer',
    ];

    public function remission(): BelongsTo
    {
        return $this->belongsTo(CommercialRemission::class, 'commercial_remission_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(CommercialRemissionItem::class, 'commercial_remission_item_id');
    }
}
