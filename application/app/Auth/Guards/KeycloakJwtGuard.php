<?php

namespace App\Auth\Guards;

use App\Services\KeycloakService;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

// This guard's purpose currently is only for machine usage over API.
// End-users will be authenticating over cookie-based solution.
class KeycloakJwtGuard implements Guard
{
    use GuardHelpers;
    private readonly KeycloakService $keycloakService;

    public function __construct(UserProvider $provider, private Request $request)
    {
        $this->provider = $provider;
        $this->keycloakService = app()->get(KeycloakService::class);
    }

    public function user(): ?\Illuminate\Contracts\Auth\Authenticatable
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $token = $this->request->bearerToken();
        if (! $token) {
            return null;
        }

        try {
            $payload = $this->keycloakService->validateToken($token);
        } catch (\Throwable) {
            return null;
        }

        // // Only allow service accounts to access over Bearer token authentication
        // if (!Str::startsWith($payload->preferred_username, 'service-account-')) {
        //     return null;
        // }

        $tolkevaravPersonalIdentificationCode = data_get($payload, 'tolkevarav.personalIdentificationCode');

        $user = $this->provider->retrieveByCredentials([
            'keycloak_sub' => $payload->sub
        ]);

        if (! $user) {
            $userModel = config('auth.providers.users.model');
            $user = new $userModel();
            $user->keycloak_sub = $payload->sub;
            $user->name = $payload->name ?? $payload->preferred_username ?? $payload->sub;
        }

        if ($tolkevaravPersonalIdentificationCode && $user->tolkevarav_personal_identification_code !== $tolkevaravPersonalIdentificationCode) {
            $user->tolkevarav_personal_identification_code = $tolkevaravPersonalIdentificationCode;
        }

        if (! $user->exists || $user->isDirty()) {
            $user->save();
        }

        return $this->user = $user;
    }

    public function validate(array $credentials = []): bool
    {
        return false;
    }

}
