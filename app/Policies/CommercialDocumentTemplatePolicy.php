<?php

namespace App\Policies;

use App\Models\CommercialDocumentTemplate;
use App\Models\User;

class CommercialDocumentTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isActive($user);
    }

    public function view(User $user, CommercialDocumentTemplate $template): bool
    {
        return $this->canAccess($user, $template);
    }

    public function create(User $user): bool
    {
        return $this->isActive($user);
    }

    public function update(User $user, CommercialDocumentTemplate $template): bool
    {
        return $this->canAccess($user, $template);
    }

    public function delete(User $user, CommercialDocumentTemplate $template): bool
    {
        return $this->canAccess($user, $template);
    }

    private function canAccess(User $user, CommercialDocumentTemplate $template): bool
    {
        if (!$this->isActive($user)) {
            return false;
        }

        return $this->isAdmin($user) || (int) $template->users_id === (int) $user->id;
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
