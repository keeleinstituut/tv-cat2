<?php

namespace Tests\Unit\Services;

use App\Services\XliffConverterService;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use Tests\TestCase;

class XliffConverterServiceTest extends TestCase
{
    public function test_client_uses_configured_base_url(): void
    {
        Http::fake(['*' => Http::response([], 200)]);

        $client = (new ReflectionMethod(XliffConverterService::class, 'client'))->invoke(null);
        $client->get('/ping');

        $expectedBase = rtrim(config('services.matecat_filters.base_url'), '/');
        Http::assertSent(fn ($request) => $request->url() === "$expectedBase/ping");
    }
}
