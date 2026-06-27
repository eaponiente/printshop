<?php

namespace Payroll\Audit\Policies;

use App\Models\User;
use Payroll\Audit\Models\AuditLog;

class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->branch_id === $auditLog->branch_id;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    public function delete(User $user, AuditLog $auditLog): bool
    {
        return false;
    }
}
