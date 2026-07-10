<?php

namespace App\Policies;

use App\Models\Segment;
use App\Models\User;
use App\Services\ProjectAccessDelegationService;

class SegmentPolicy
{
    public function __construct(private readonly ProjectAccessDelegationService $delegation)
    {
    }

    public function viewAny(User $user): bool
    {
        return $user->isServiceAccount();
    }

    public function view(User $user, Segment $segment): bool
    {
        return $user->isServiceAccount() || $this->delegation->allows($user, $segment->job->project_id, 'view');
    }

    // Submitting a translation is part of "view"-level CAT-tool access — anyone who can
    // see the project (including an accepted partner institution) can translate.
    public function update(User $user, Segment $segment): bool
    {
        return $user->isServiceAccount() || $this->delegation->allows($user, $segment->job->project_id, 'view');
    }
}
