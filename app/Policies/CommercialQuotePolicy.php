<?php

namespace App\Policies;

use App\Models\CommercialQuote;
use App\Models\User;

class CommercialQuotePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isActive($user);
    }

    public function view(User $user, CommercialQuote $commercialQuote): bool
    {
        return $this->canAccess($user, $commercialQuote);
    }

    public function create(User $user): bool
    {
        return $this->isActive($user);
    }

    public function update(User $user, CommercialQuote $commercialQuote): bool
    {
        return $this->canAccess($user, $commercialQuote);
    }

    public function delete(User $user, CommercialQuote $commercialQuote): bool
    {
        return $this->canAccess($user, $commercialQuote) && $commercialQuote->canBeDeleted();
    }

    private function canAccess(User $user, CommercialQuote $commercialQuote): bool
    {
        if (!$this->isActive($user)) {
            return false;
        }

        return $this->isAdmin($user)
            || (int) $commercialQuote->users_id === (int) $user->id
            || (int) $commercialQuote->created_by_id === (int) $user->id
            || (int) $commercialQuote->assigned_user_id === (int) $user->id;
    }

    private function isActive(User $user): bool
    {
        return (int) ($user->active ?? 1) === 1;
    }

    private function isAdmin(User $user): bool
    {
        $role = strtoupper((string) ($user->rol ?? ''));

        return (int) ($user->admin ?? 0) === 1 || str_contains($role, 'ADMIN');
    }
}
