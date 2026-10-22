<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * GET /api/healthz (DIQ-1303): checks the database, cache, queue backend and
 * document storage, so an uptime monitor catches a broken dependency and
 * not just a running web server. 503 when any check fails. The response
 * names only which check failed, never connection details.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->check(fn () => DB::select('select 1')),
            'cache' => $this->check(function () {
                $key = 'healthz:'.Str::random(8);
                Cache::put($key, 'ok', 10);
                $ok = Cache::pull($key) === 'ok';
                if (! $ok) {
                    throw new \RuntimeException('cache round-trip failed');
                }
            }),
            'queue' => $this->check(function () {
                if (config('queue.default') === 'redis') {
                    Redis::connection(config('queue.connections.redis.connection', 'default'))->ping();
                }
            }),
            'storage' => $this->check(function () {
                $disk = Storage::disk(config('filesystems.documents_disk', 'local'));
                $path = 'healthz/'.Str::random(8);
                $disk->put($path, 'ok');
                $disk->delete($path);
            }),
        ];

        $healthy = ! in_array('fail', $checks, true);

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'service' => 'driveq-api',
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }

    private function check(callable $probe): string
    {
        try {
            $probe();

            return 'ok';
        } catch (Throwable $e) {
            report($e);

            return 'fail';
        }
    }
}
