<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialQuoteStatusHistory extends Model
{
    protected $table = 'commercial_quote_status_history';

    protected $fillable = [
        'commercial_quote_id',
        'old_status',
        'new_status',
        'user_id',
        'note',
        'changed_at',
    ];

    protected $casts = [
        'commercial_quote_id' => 'integer',
        'user_id' => 'integer',
        'changed_at' => 'datetime',
    ];

    public function quote(): BelongsTo
    {
        return $this->belongsTo(CommercialQuote::class, 'commercial_quote_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
