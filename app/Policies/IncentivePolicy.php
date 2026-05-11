<?php

namespace App\Policies;

use App\Models\User;

class IncentivePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isAdmin();
    }

    public function pay(User $user): bool
    {
        return $user->isSuperAdmin();
    }
}
