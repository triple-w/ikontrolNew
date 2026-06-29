<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialContact extends Model
{
    protected $fillable = [
        'commercial_client_id',
        'name',
        'position',
        'email',
        'phone',
        'mobile',
        'is_primary',
        'receives_quotes',
        'receives_documents',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'commercial_client_id' => 'integer',
        'is_primary' => 'boolean',
        'receives_quotes' => 'boolean',
        'receives_documents' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function commercialClient(): BelongsTo
    {
        return $this->belongsTo(CommercialClient::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
