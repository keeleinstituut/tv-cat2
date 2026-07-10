<?php

namespace App\Policies;

use App\Models\Job;
use App\Models\User;
use App\Services\ProjectAccessDelegationService;

class JobPolicy
{
    public function __construct(private readonly ProjectAccessDelegationService $delegation)
    {
    }

    public function viewAny(User $user): bool
    {
        return $user->isServiceAccount();
    }

    public function view(User $user, Job $job): bool
    {
        return $user->isServiceAccount() || $this->delegation->allows($user, $job->project_id, 'view');
    }

    public function create(User $user, Job $job): bool
    {
        return $user->isServiceAccount() || $this->delegation->allows($user, $job->project_id, 'update');
    }

    // Also gates mutating actions that don't have a dedicated ability, e.g. pretranslate —
    // translating is part of "view"-level CAT-tool access (see SegmentPolicy::update).
    public function update(User $user, Job $job): bool
    {
        return $user->isServiceAccount() || $this->delegation->allows($user, $job->project_id, 'view');
    }

    public function delete(User $user, Job $job): bool
    {
        return $user->isServiceAccount() || $this->delegation->allows($user, $job->project_id, 'update');
    }
}
