<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialRemissionStatusHistory extends Model
{
    protected $table = 'commercial_remission_status_history';

    protected $fillable = [
        'commercial_remission_id',
        'old_status',
        'new_status',
        'user_id',
        'note',
        'changed_at',
    ];

    protected $casts = [
        'commercial_remission_id' => 'integer',
        'user_id' => 'integer',
        'changed_at' => 'datetime',
    ];

    public function remission(): BelongsTo
    {
        return $this->belongsTo(CommercialRemission::class, 'commercial_remission_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
