<?php

namespace App\Auth\Guards;

use App\Services\KeycloakService;
use Firebase\JWT\JWT;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;

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
            $payload = $this->validateToken($token);
        } catch (\Throwable) {
            return null;
        }

        $user = $this->provider->retrieveByCredentials([
            'keycloak_sub' => $payload->sub
        ]);

        if (! $user) {
            $userModel = config('auth.providers.users.model');
            $user = new $userModel();
            $user->keycloak_sub = $payload->sub;
            $user->name = $payload->name ?? $payload->preferred_username ?? $payload->sub;
            $user->save();
        }

        return $this->user = $user;
    }

    public function validate(array $credentials = []): bool
    {
        return false;
    }

    private function validateToken(string $token): object
    {
        $jwks = $this->keycloakService->retrieveJwks();
        $decoded = JWT::decode($token, $jwks);

        if ($decoded->iss !== $this->keycloakService->getRealmUrl()) {
            throw new \RuntimeException('Invalid token issuer');
        }

        $aud = (array) $decoded->aud;
        if (! in_array($this->keycloakService->getClientId(), $aud, true) && ! in_array('account', $aud, true)) {
            throw new \RuntimeException('Invalid token audience');
        }

        return $decoded;
    }
}
