<?php

namespace Tests\Unit\Policies;

use App\Models\Analysis;
use App\Models\Job;
use App\Models\Project;
use App\Models\Segment;
use App\Models\User;
use App\Policies\AnalysisPolicy;
use App\Policies\JobPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\SegmentPolicy;
use App\Services\ProjectAccessDelegationService;
use Tests\TestCase;

class DelegatedAuthorizationPoliciesTest extends TestCase
{
    private function fakeDelegation(string $allowedProjectId, string $allowedAbility): ProjectAccessDelegationService
    {
        return new class($allowedProjectId, $allowedAbility) extends ProjectAccessDelegationService {
            public function __construct(private string $allowedProjectId, private string $allowedAbility)
            {
            }

            public function allows(User $user, string $projectId, string $ability): bool
            {
                return $projectId === $this->allowedProjectId && $ability === $this->allowedAbility;
            }
        };
    }

    private function normalUser(): User
    {
        return new User(['name' => 'alice']);
    }

    private function serviceAccount(): User
    {
        return new User(['name' => 'service-account-x']);
    }

    public function test_project_policy_delegates_view_and_denies_other_projects(): void
    {
        $delegation = $this->fakeDelegation('project-a', 'view');
        $policy = new ProjectPolicy($delegation);

        $projectA = tap(new Project(['name' => 'A', 'source_locale' => 'en']))->setAttribute('id', 'project-a');
        $projectB = tap(new Project(['name' => 'B', 'source_locale' => 'en']))->setAttribute('id', 'project-b');
        $user = $this->normalUser();

        $this->assertTrue($policy->view($user, $projectA));
        $this->assertFalse($policy->view($user, $projectB));
    }

    public function test_project_policy_update_and_view_are_independent_abilities(): void
    {
        $delegation = $this->fakeDelegation('project-a', 'view');
        $policy = new ProjectPolicy($delegation);

        $project = tap(new Project(['name' => 'A', 'source_locale' => 'en']))->setAttribute('id', 'project-a');

        $this->assertTrue($policy->view($this->normalUser(), $project));
        $this->assertFalse($policy->update($this->normalUser(), $project));
        $this->assertFalse($policy->delete($this->normalUser(), $project));
    }

    public function test_project_policy_create_and_view_any_are_service_account_only(): void
    {
        $delegation = $this->fakeDelegation('project-a', 'view');
        $policy = new ProjectPolicy($delegation);

        $project = tap(new Project(['name' => 'A', 'source_locale' => 'en']))->setAttribute('id', 'project-a');

        $this->assertFalse($policy->create($this->normalUser(), $project));
        $this->assertFalse($policy->viewAny($this->normalUser()));
        $this->assertTrue($policy->create($this->serviceAccount(), $project));
        $this->assertTrue($policy->viewAny($this->serviceAccount()));
    }

    public function test_service_account_bypasses_every_ability(): void
    {
        $delegation = $this->fakeDelegation('nothing-matches', 'view');
        $policy = new ProjectPolicy($delegation);
        $project = tap(new Project(['name' => 'A', 'source_locale' => 'en']))->setAttribute('id', 'project-a');
        $service = $this->serviceAccount();

        $this->assertTrue($policy->view($service, $project));
        $this->assertTrue($policy->update($service, $project));
        $this->assertTrue($policy->delete($service, $project));
    }

    public function test_job_policy_view_and_update_delegate_via_project_id(): void
    {
        $delegation = $this->fakeDelegation('project-a', 'view');
        $policy = new JobPolicy($delegation);

        $jobA = new Job(['project_id' => 'project-a', 'target_locale' => 'et']);
        $jobB = new Job(['project_id' => 'project-b', 'target_locale' => 'et']);
        $user = $this->normalUser();

        $this->assertTrue($policy->view($user, $jobA));
        $this->assertFalse($policy->view($user, $jobB));
        // pretranslate is gated by update(), which checks 'view'-level access, matching
        // the "translating is view-level CAT access" decision.
        $this->assertTrue($policy->update($user, $jobA));
        // create/delete need 'update'-ability delegation (manage-level), not granted here.
        $this->assertFalse($policy->create($user, $jobA));
        $this->assertFalse($policy->delete($user, $jobA));
    }

    public function test_segment_policy_view_and_update_delegate_via_job_project(): void
    {
        $delegation = $this->fakeDelegation('project-a', 'view');
        $policy = new SegmentPolicy($delegation);

        $job = new Job(['project_id' => 'project-a', 'target_locale' => 'et']);
        $segment = new Segment(['job_id' => $job->id]);
        $segment->setRelation('job', $job);
        $user = $this->normalUser();

        $this->assertTrue($policy->view($user, $segment));
        $this->assertTrue($policy->update($user, $segment));
    }

    public function test_analysis_policy_view_uses_view_ability_and_mutations_use_update_ability(): void
    {
        $delegation = $this->fakeDelegation('project-a', 'view');
        $policy = new AnalysisPolicy($delegation);

        $analysis = new Analysis();
        $analysis->project_id = 'project-a';
        $user = $this->normalUser();

        $this->assertTrue($policy->view($user, $analysis));
        $this->assertFalse($policy->create($user, $analysis));
        $this->assertFalse($policy->delete($user, $analysis));
    }
}
