<?php

namespace App\Auth\Guards;

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KeycloakJwtGuard implements Guard
{
    use GuardHelpers;

    public function __construct(UserProvider $provider, private Request $request)
    {
        $this->provider = $provider;
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

        $user = $this->provider->retrieveByCredentials(['keycloak_sub' => $payload->sub]);

        if (! $user) {
            $user = $this->provider->retrieveByCredentials(['email' => $payload->email ?? null]);
        }

        if (! $user) {
            $userModel = config('auth.providers.users.model');
            $user = new $userModel();
            $user->keycloak_sub = $payload->sub;
            $user->name = $payload->name ?? $payload->preferred_username ?? $payload->sub;
            // $user->email = $payload->email ?? ($payload->sub . '@keycloak.local');
            // $user->password = '';
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
        $keys = Cache::remember('keycloak_jwks', 3600, function () {
            $response = Http::get(config('keycloak.jwks_uri'));
            return $response->json();
        });

        $realmUrl = config('keycloak.realm_url');
        $clientId = config('keycloak.client_id');

        $keysParsed = JWK::parseKeySet($keys);

        $decoded = JWT::decode($token, $keysParsed);

        if ($decoded->iss !== $realmUrl) {
            throw new \RuntimeException('Invalid token issuer');
        }

        $aud = (array) $decoded->aud;
        if (! in_array($clientId, $aud, true) && ! in_array('account', $aud, true)) {
            throw new \RuntimeException('Invalid token audience');
        }

        return $decoded;
    }
}
