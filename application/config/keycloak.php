<?php

return [
    'realm_url'     => env('KEYCLOAK_REALM_URL'),
    'client_id'     => env('KEYCLOAK_CLIENT_ID'),
    'client_secret' => env('KEYCLOAK_CLIENT_SECRET'),
    'jwks_uri'      => env('KEYCLOAK_JWKS_URI'),
    'redirect_uri'  => env('KEYCLOAK_REDIRECT_URI'),
];
