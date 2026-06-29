<?php

namespace App\Policies;

use App\Models\CommercialClient;
use App\Models\User;

class CommercialClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isActive($user);
    }

    public function view(User $user, CommercialClient $commercialClient): bool
    {
        return $this->canAccess($user, $commercialClient);
    }

    public function create(User $user): bool
    {
        return $this->isActive($user);
    }

    public function update(User $user, CommercialClient $commercialClient): bool
    {
        return $this->canAccess($user, $commercialClient);
    }

    public function delete(User $user, CommercialClient $commercialClient): bool
    {
        return $this->canAccess($user, $commercialClient);
    }

    private function canAccess(User $user, CommercialClient $commercialClient): bool
    {
        if (!$this->isActive($user)) {
            return false;
        }

        return $this->isAdmin($user)
            || (int) $commercialClient->users_id === (int) $user->id
            || (int) $commercialClient->assigned_user_id === (int) $user->id;
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
