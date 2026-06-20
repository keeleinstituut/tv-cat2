<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        session(['auth_redirect' => $request->query('redirect', '/')]);

        return Socialite::driver('keycloak')->redirect();
    }

    public function callback()
    {
        $keycloakUser = Socialite::driver('keycloak')->user();

        $user = User::updateOrCreate(
            ['keycloak_sub' => $keycloakUser->getId()],
            [
                'name'  => $keycloakUser->getName() ?? $keycloakUser->getNickname() ?? $keycloakUser->getId(),
                // 'email' => $keycloakUser->getEmail() ?? ($keycloakUser->getId() . '@keycloak.local'),
            ]
        );

        Auth::guard('web')->login($user, remember: true);

        return redirect(session()->pull('auth_redirect', '/'));
    }

    public function user(Request $request)
    {
        return response()->json(['user' => $request->user()]);
    }
}
