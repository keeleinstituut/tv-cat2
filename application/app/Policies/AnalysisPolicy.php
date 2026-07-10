<?php

namespace App\Policies;

use App\Models\Analysis;
use App\Models\User;
use App\Services\ProjectAccessDelegationService;

class AnalysisPolicy
{
    public function __construct(private readonly ProjectAccessDelegationService $delegation)
    {
    }

    public function viewAny(User $user): bool
    {
        return $user->isServiceAccount();
    }

    public function view(User $user, Analysis $analysis): bool
    {
        return $user->isServiceAccount() || $this->delegation->allows($user, $analysis->project_id, 'view');
    }

    public function create(User $user, Analysis $analysis): bool
    {
        return $user->isServiceAccount() || $this->delegation->allows($user, $analysis->project_id, 'update');
    }

    public function delete(User $user, Analysis $analysis): bool
    {
        return $user->isServiceAccount() || $this->delegation->allows($user, $analysis->project_id, 'update');
    }
}
