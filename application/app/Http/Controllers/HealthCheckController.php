<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Exception;

class HealthCheckController extends Controller
{
    /**
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            DB::select('SELECT 1');

            Redis::command('PING');

            return response()->json(['status' => 'ok']);
        } catch (Exception $e) {
            Log::error('Health check failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Health check failed'
            ], 503);
        }
    }
}

