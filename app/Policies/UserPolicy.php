<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function manageStaff(User $user): bool
    {
        return $user->isAdmin() && filled($user->business_id);
    }

    public function update(User $user, User $targetUser): bool
    {
        return $user->id === $targetUser->id || $this->canManageBusinessUser($user, $targetUser);
    }

    public function delete(User $user, User $targetUser): bool
    {
        return !$targetUser->isAdmin() && $this->canManageBusinessUser($user, $targetUser);
    }

    private function canManageBusinessUser(User $user, User $targetUser): bool
    {
        return $user->isAdmin()
            && filled($user->business_id)
            && $user->business_id === $targetUser->business_id;
    }
}
