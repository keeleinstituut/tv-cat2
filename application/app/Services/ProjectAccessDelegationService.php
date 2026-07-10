<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delegates the actual authorization decision for a tv-translation-order-linked
 * project to tv-translation-order itself, live, using Catto's own service-account
 * credentials — never the end user's token. tv-translation-order is the source of
 * truth for who may access a project; nothing about that decision is replicated here.
 */
class ProjectAccessDelegationService
{
    public function __construct(private readonly KeycloakService $keycloakService)
    {
    }

    public function allows(User $user, string $projectId, string $ability): bool
    {
        $pic = $user->tolkevarav_personal_identification_code;

        if (empty($pic)) {
            return false;
        }

        $ttl = (int) config('services.tv_translation_order.authorization_cache_ttl', 45);
        $cacheKey = "catto-authorization/$projectId/$pic/$ability";

        return Cache::remember($cacheKey, $ttl, function () use ($pic, $projectId, $ability) {
            return $this->check($pic, $projectId, $ability);
        });
    }

    private function check(string $pic, string $projectId, string $ability): bool
    {
        $baseUrl = rtrim(config('services.tv_translation_order.base_url'), '/');

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->keycloakService->retrieveServiceAccountAccessToken(),
            ])->get("$baseUrl/api/catto-authorization", [
                'catto_project_id' => $projectId,
                'personal_identification_code' => $pic,
                'ability' => $ability,
            ]);
        } catch (Throwable $e) {
            Log::warning('Catto authorization delegation call failed', ['exception' => $e->getMessage()]);
            return false;
        }

        if (! $response->successful()) {
            Log::warning('Catto authorization delegation call returned a non-successful response', [
                'status' => $response->status(),
            ]);
            return false;
        }

        return (bool) data_get($response->json(), 'allowed', false);
    }
}
