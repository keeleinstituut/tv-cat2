<?php

namespace App\Policies\Concerns;

use App\Models\User;

class TenantScope
{
    /**
     * Returns null when the user is unrestricted (service account), or the list of
     * tenant ids (possibly empty) the user holds the given privilege for.
     *
     * @return array<int, string>|null
     */
    public static function allowedTenantIds(User $user, string $privilege): ?array
    {
        if ($user->isServiceAccount()) {
            return null;
        }

        return $user->userPrivileges()?->getTenantsForPrivilege($privilege) ?? [];
    }
}
