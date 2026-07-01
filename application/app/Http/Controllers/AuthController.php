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
        $tolkevaravInstitutionId = data_get($tolkevaravClaim, 'selectedInstitution.id');
        $tolkevaravInstitutionUserId = data_get($tolkevaravClaim, 'institutionUserId');
        $tolkevaravName = $tolkevaravForename . ' ' . $tolkevaravSurname;
        $keycloakName = $keycloakUser->getName() ?? $keycloakUser->getNickname() ?? $keycloakUser->getId();

        $user = User::updateOrCreate(
            [
                'keycloak_sub' => $keycloakUser->getId(),
                'tolkevarav_institution_id' => $tolkevaravInstitutionId,
                'tolkevarav_institution_user_id' => $tolkevaravInstitutionUserId,
            ],
            [
                'name' => !empty(str_replace(' ', '', $tolkevaravName)) ? $tolkevaravName : $keycloakName,
            ]
        );

        // dump([
        //     'iid' => $tolkevaravInstitutionId,
        //     'iuid' => $tolkevaravInstitutionUserId,
        //     'keyucloakUser' => $keycloakUser,
        //     'methods' => get_class_methods($keycloakUser),
        //     'test' => $keycloakUser->getRaw(),
        //     'test2' => $tolkevaravClaim,
        //     'user' => $user,
        // ]);
        // return;

        Auth::guard('web')->login($user, remember: false);

        return redirect(session()->pull('auth_redirect', '/'));
    }

    public function user(Request $request)
    {
        return response()->json(['user' => $request->user()]);
    }
}
