<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthController
{
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'app' => config('app.name'),
            'time' => now()->toIso8601String(),
        ]);
    }

    public function database(): JsonResponse
    {
        try {
            DB::select('select 1');

            return response()->json(['status' => 'ok', 'database' => config('database.default')]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 503);
        }
    }

    public function queue(): JsonResponse
    {
        $ok = config('queue.default') !== '';
        $driver = config('queue.default');

        return response()->json([
            'status' => $ok ? 'ok' : 'error',
            'queue_driver' => $driver,
        ], $ok ? 200 : 503);
    }

    public function cache(): JsonResponse
    {
        try {
            Cache::put('health.check', now(), 10);

            return response()->json(['status' => 'ok', 'store' => config('cache.default')]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 503);
        }
    }
}
