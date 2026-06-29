<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommercialClient extends Model
{
    protected $fillable = [
        'users_id',
        'assigned_user_id',
        'name',
        'business_name',
        'client_type',
        'email',
        'phone',
        'mobile',
        'street',
        'exterior_number',
        'interior_number',
        'neighborhood',
        'city',
        'state',
        'country',
        'postal_code',
        'category',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'users_id' => 'integer',
        'assigned_user_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'users_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CommercialContact::class);
    }

    public function primaryContact(): HasMany
    {
        return $this->contacts()->where('is_primary', true)->where('is_active', true);
    }

    public function fiscalClients(): BelongsToMany
    {
        return $this->belongsToMany(Cliente::class, 'commercial_client_fiscal_client', 'commercial_client_id', 'fiscal_client_id')
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function defaultFiscalClient(): BelongsToMany
    {
        return $this->fiscalClients()->wherePivot('is_default', true);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('users_id', $userId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeVisibleToQuoteUser(Builder $query, User $user): Builder
    {
        $role = strtoupper((string) ($user->rol ?? ''));
        $isAdmin = (int) ($user->admin ?? 0) === 1 || str_contains($role, 'ADMIN');

        if ($isAdmin) {
            return $query;
        }

        return $query->where(function ($visible) use ($user) {
            $visible->where('users_id', $user->id)
                ->orWhere('assigned_user_id', $user->id);
        });
    }
}
