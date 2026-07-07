<?php

namespace App\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;


class KeycloakService
{
    private $baseUrl;
    private $clientId;
    private $clientSecret;
    private $realm;

    public function __construct() {
        $this->baseUrl = config('services.keycloak.base_url');
        $this->clientId = config('services.keycloak.client_id');
        $this->clientSecret = config('services.keycloak.client_secret');
        $this->realm = config('services.keycloak.realms');
    }

    public function getRealmUrl() {
        return "$this->baseUrl/realms/$this->realm";
    }

    public function getJwksUrl() {
        $realmUrl = $this->getRealmUrl();
        return "$realmUrl/protocol/openid-connect/certs";
    }

    public function getTokenUrl() {
        $realmUrl = $this->getRealmUrl();
        return "$realmUrl/protocol/openid-connect/token";
    }

    public function getBaseUrl() {
        return $this->baseUrl;
    }

    public function getRealm() {
        return $this->realm;
    }

    public function getClientId() {
        return $this->clientId;
    }

    public function getClientSecret() {
        return $this->clientSecret;
    }

    public function retrieveJwks() {
        $keys = Cache::remember('keycloak_jwks', 3600, function () {
            $response = Http::get($this->getJwksUrl());
            return $response->json();
        });

        return JWK::parseKeySet($keys);
    }

    public function retrieveServiceAccountAccessToken() {
        return $this->retrieveServiceAccountTokens()['access_token'];
    }

    public function retrieveServiceAccountTokens() {
        $realm = $this->getRealm();
        $clientId = $this->getClientId();
        $cacheKey = "keycloak-service-account/$realm/$clientId/token-response";

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $response = Http::asForm()->post($this->getTokenUrl(), [
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ])->throw()->json();

        // Adjust cache TTL to be shorter than token TTL to ensure some leeway for token usage.
        $ttl = max([0, $response['expires_in'] - 300]);

        if ($ttl > 0) {
            Cache::set($cacheKey, $response, $ttl);
        }

        return $response;
    }

    public function validateToken(string $token): object
    {
        $jwks = $this->retrieveJwks();
        $decoded = JWT::decode($token, $jwks);

        if ($decoded->iss !== $this->getRealmUrl()) {
            throw new \RuntimeException('Invalid token issuer');
        }

        $aud = (array) $decoded->aud;
        if (! in_array($this->getClientId(), $aud, true) && ! in_array('account', $aud, true)) {
            throw new \RuntimeException('Invalid token audience');
        }

        return $decoded;
    }
}
