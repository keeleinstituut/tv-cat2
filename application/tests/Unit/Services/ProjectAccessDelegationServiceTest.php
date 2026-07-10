<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\KeycloakService;
use App\Services\ProjectAccessDelegationService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProjectAccessDelegationServiceTest extends TestCase
{
    private function serviceWithFakeKeycloak(): ProjectAccessDelegationService
    {
        $fakeKeycloak = new class extends KeycloakService {
            public function __construct()
            {
            }

            public function retrieveServiceAccountAccessToken()
            {
                return 'fake-service-token';
            }
        };

        return new ProjectAccessDelegationService($fakeKeycloak);
    }

    public function test_user_with_no_personal_identification_code_is_denied_without_any_http_call(): void
    {
        Http::fake(function () {
            $this->fail('should not have made an HTTP call');
        });

        $user = new User(['name' => 'bob']);
        $service = $this->serviceWithFakeKeycloak();

        $this->assertFalse($service->allows($user, 'proj-1', 'view'));
    }

    public function test_successful_allowed_true_response_grants_access(): void
    {
        Http::fake(['*catto-authorization*' => Http::response(['allowed' => true], 200)]);

        $user = new User(['name' => 'alice', 'tolkevarav_personal_identification_code' => 'PIC-1']);
        $service = $this->serviceWithFakeKeycloak();

        $this->assertTrue($service->allows($user, 'proj-1', 'view'));
    }

    public function test_successful_allowed_false_response_denies_access(): void
    {
        Http::fake(['*catto-authorization*' => Http::response(['allowed' => false], 200)]);

        $user = new User(['name' => 'alice', 'tolkevarav_personal_identification_code' => 'PIC-1']);
        $service = $this->serviceWithFakeKeycloak();

        $this->assertFalse($service->allows($user, 'proj-1', 'view'));
    }

    public function test_network_error_fails_closed(): void
    {
        Http::fake(function () {
            throw new ConnectionException('simulated outage');
        });

        $user = new User(['name' => 'alice', 'tolkevarav_personal_identification_code' => 'PIC-1']);
        $service = $this->serviceWithFakeKeycloak();

        $this->assertFalse($service->allows($user, 'proj-1', 'view'));
    }

    public function test_non_successful_response_fails_closed(): void
    {
        Http::fake(['*catto-authorization*' => Http::response(['error' => 'forbidden'], 403)]);

        $user = new User(['name' => 'alice', 'tolkevarav_personal_identification_code' => 'PIC-1']);
        $service = $this->serviceWithFakeKeycloak();

        $this->assertFalse($service->allows($user, 'proj-1', 'view'));
    }

    public function test_result_is_cached_within_ttl(): void
    {
        Http::fake(['*catto-authorization*' => Http::response(['allowed' => true], 200)]);

        $user = new User(['name' => 'alice', 'tolkevarav_personal_identification_code' => 'PIC-1']);
        $service = $this->serviceWithFakeKeycloak();

        $first = $service->allows($user, 'proj-cache', 'view');

        // Even though the underlying "server" would now say false, the cached
        // value from the first call should still be returned.
        Http::fake(['*catto-authorization*' => Http::response(['allowed' => false], 200)]);
        $second = $service->allows($user, 'proj-cache', 'view');

        $this->assertTrue($first);
        $this->assertTrue($second);
    }
}
