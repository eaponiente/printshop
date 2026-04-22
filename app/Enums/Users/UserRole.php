<?php

namespace App\Enums\Users;

enum UserRole: string
{
    // add const to all user roles
    case ADMIN = 'admin';

    case SUPERADMIN = 'superadmin';

    case STAFF = 'staff';
}
