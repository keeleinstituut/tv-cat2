<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Services\ProjectAccessDelegationService;

class ProjectPolicy
{
    public function __construct(private readonly ProjectAccessDelegationService $delegation)
    {
    }

    public function viewAny(User $user): bool
    {
        return $user->isServiceAccount();
    }

    public function view(User $user, Project $project): bool
    {
        return $user->isServiceAccount() || $this->delegation->allows($user, $project->id, 'view');
    }

    public function create(User $user, Project $project): bool
    {
        return $user->isServiceAccount();
    }

    public function update(User $user, Project $project): bool
    {
        return $user->isServiceAccount() || $this->delegation->allows($user, $project->id, 'update');
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->isServiceAccount() || $this->delegation->allows($user, $project->id, 'update');
    }
}
