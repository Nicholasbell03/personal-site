<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class MeasureRequestPerformance
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // LARAVEL_START is defined at the very beginning of public/index.php
        $phpStart = defined('LARAVEL_START') ? LARAVEL_START : microtime(true);
        $middlewareStart = microtime(true);

        // Time since PHP started (includes autoloading, framework boot)
        $bootstrapTime = ($middlewareStart - $phpStart) * 1000;

        // Enable query logging
        DB::enableQueryLog();

        // Track time to first DB connection
        $dbConnectStart = microtime(true);
        try {
            DB::connection()->getPdo();
            $dbConnectTime = (microtime(true) - $dbConnectStart) * 1000;
        } catch (\Exception $e) {
            $dbConnectTime = -1;
        }

        // Execute the actual request
        $controllerStart = microtime(true);
        $response = $next($request);
        $controllerTime = (microtime(true) - $controllerStart) * 1000;

        // Get query stats
        $queries = DB::getQueryLog();
        $queryCount = count($queries);
        $queryTime = array_sum(array_column($queries, 'time'));

        // Total time
        $totalTime = (microtime(true) - $phpStart) * 1000;

        // Build timing breakdown
        $timing = [
            'total_ms' => round($totalTime, 2),
            'bootstrap_ms' => round($bootstrapTime, 2),
            'db_connect_ms' => round($dbConnectTime, 2),
            'controller_ms' => round($controllerTime, 2),
            'query_count' => $queryCount,
            'query_time_ms' => round($queryTime, 2),
            'queries' => array_map(fn ($q) => [
                'sql' => $q['query'],
                'time_ms' => $q['time'],
            ], $queries),
        ];

        // Log it
        Log::channel('stderr')->info('Request Performance', [
            'uri' => $request->getRequestUri(),
            'method' => $request->method(),
            'timing' => $timing,
        ]);

        // Add timing headers to response for easy inspection
        $response->headers->set('X-Timing-Total-Ms', (string) round($totalTime, 2));
        $response->headers->set('X-Timing-Bootstrap-Ms', (string) round($bootstrapTime, 2));
        $response->headers->set('X-Timing-DB-Connect-Ms', (string) round($dbConnectTime, 2));
        $response->headers->set('X-Timing-Controller-Ms', (string) round($controllerTime, 2));
        $response->headers->set('X-Timing-Query-Count', (string) $queryCount);
        $response->headers->set('X-Timing-Query-Ms', (string) round($queryTime, 2));

        return $response;
    }
}
