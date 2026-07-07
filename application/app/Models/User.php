<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\AuthorizationService;
use App\Services\Dto\UserPrivileges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'keycloak_sub',
        'tolkevarav_personal_identification_code',
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function isServiceAccount() {
        return Str::startsWith($this->name, 'service-account-');
    }

    public function userPrivileges(): ?UserPrivileges {
        $pic = $this->tolkevarav_personal_identification_code;

        if (!$pic) {
            return null;
        }

        $authorizationService = app()->get(AuthorizationService::class);
        return $authorizationService->retrieveUserPrivileges($pic);
    }
}
