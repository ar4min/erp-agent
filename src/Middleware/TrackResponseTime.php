<?php

namespace Ar4min\ErpAgent\Middleware;

use Ar4min\ErpAgent\Services\HeartbeatService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackResponseTime
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        $response = $next($request);

        $duration = (microtime(true) - $startTime) * 1000; // Convert to ms

        HeartbeatService::recordResponseTime($duration);

        return $response;
    }
}
