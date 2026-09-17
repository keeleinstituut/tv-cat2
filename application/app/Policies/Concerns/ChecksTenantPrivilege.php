<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait ChecksTenantPrivilege
{
    protected function userHasPrivilege(User $user, ?string $tenantId, string $privilege): bool
    {
        return $user->isServiceAccount()
            || ($user->userPrivileges()?->hasPrivilege($tenantId, $privilege) ?? false);
    }

    protected function tenantsWithPrivilege(User $user, string $privilege): array
    {
        return $user->isServiceAccount() ? [] : ($user->userPrivileges()?->getTenantsForPrivilege($privilege) ?? []);
    }
}
