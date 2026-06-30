<?php

namespace App\Policies;

use App\Models\CommercialRemission;
use App\Models\User;

class CommercialRemissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isActive($user);
    }

    public function view(User $user, CommercialRemission $commercialRemission): bool
    {
        return $this->canAccess($user, $commercialRemission);
    }

    public function create(User $user): bool
    {
        return $this->isActive($user);
    }

    public function update(User $user, CommercialRemission $commercialRemission): bool
    {
        return $this->canAccess($user, $commercialRemission);
    }

    private function canAccess(User $user, CommercialRemission $commercialRemission): bool
    {
        if (!$this->isActive($user)) {
            return false;
        }

        return $this->isAdmin($user)
            || (int) $commercialRemission->users_id === (int) $user->id
            || (int) $commercialRemission->created_by_id === (int) $user->id
            || (int) $commercialRemission->assigned_user_id === (int) $user->id;
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
