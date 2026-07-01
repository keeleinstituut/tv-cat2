<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Manager\OAuth2\User as SocialiteUser;

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

        
        $tolkevaravClaim = data_get($keycloakUser->getRaw(), 'tolkevarav');
        $tolkevaravForename = data_get($tolkevaravClaim, 'forename');
        $tolkevaravSurname = data_get($tolkevaravClaim, 'surname');
        $tolkevaravPersonalIdentificationCode = data_get($tolkevaravClaim, 'personalIdentificationCode');
        $tolkevaravName = $tolkevaravForename . ' ' . $tolkevaravSurname;
        $keycloakName = $keycloakUser->getName() ?? $keycloakUser->getNickname() ?? $keycloakUser->getId();

        $user = User::updateOrCreate(
            [
                'keycloak_sub' => $keycloakUser->getId(),
            ],
            [
                'name' => !empty(str_replace(' ', '', $tolkevaravName)) ? $tolkevaravName : $keycloakName,
                'tolkevarav_personal_identification_code' => $tolkevaravPersonalIdentificationCode,
            ]
        );

        Auth::guard('web')->login($user, remember: false);

        return redirect(session()->pull('auth_redirect', '/'));
    }

    public function user(Request $request)
    {
        return response()->json(['user' => $request->user()]);
    }
}
