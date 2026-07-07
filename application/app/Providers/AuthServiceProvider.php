<?php

namespace App\Providers;

use App\Auth\Guards\KeycloakJwtGuard;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        Auth::extend('keycloak-jwt', function ($app, $name, $config) {
            return new KeycloakJwtGuard(
                Auth::createUserProvider($config['provider']),
                $app['request']
            );
        });
    }
}
