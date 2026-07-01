<?php

namespace App\Services;

use App\Services\Dto\UserPrivileges;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;


class AuthorizationService
{
    private $baseUrl;

    public function __construct(private readonly KeycloakService $keycloakService) {
        $this->baseUrl = config('services.tv_authorization.base_url');
    }

    public function retrieveUserJwtClaims(string $institutionUserId) {
        $cacheKey = "jwt-claims/$institutionUserId";
        $url = "$this->baseUrl/api/jwt-claims?institution_user_id=$institutionUserId";

        return Cache::remember($cacheKey, 3600, function () use ($url) {
            $response = $this->client()->get($url);
            return $response->throw()->json();
        });
    }

    public function retrieveUserPrivileges(string $personalIdentificationCode): UserPrivileges {
        $cacheKey = "user-privileges/$personalIdentificationCode";
        $url = "$this->baseUrl/api/user-privileges?personal_identification_code=$personalIdentificationCode";

        $fn = function () use ($url) {
            $response = $this->client()->get($url);
            return $response->throw()->json();
        };

        $response = Cache::remember($cacheKey, 3600, $fn);
        // $response = $fn();

        return new UserPrivileges($response['data']);
    }

    private function client() {
        return Http::baseUrl($this->baseUrl)
            ->withHeader('Accept', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->keycloakService->retrieveServiceAccountAccessToken());
    }
}
