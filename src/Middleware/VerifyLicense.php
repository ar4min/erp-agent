<?php

namespace Ar4min\ErpAgent\Middleware;

use Ar4min\ErpAgent\Services\LicenseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyLicense
{
    protected LicenseService $licenseService;

    public function __construct(LicenseService $licenseService)
    {
        $this->licenseService = $licenseService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if route is in exception list
        if ($this->isExcepted($request)) {
            return $next($request);
        }

        // Check license validity
        if (!$this->licenseService->isValid()) {
            return $this->handleInvalidLicense($request);
        }

        // Add license info to request for use in controllers
        $request->attributes->set('license_status', $this->licenseService->getStatus());

        return $next($request);
    }

    /**
     * Check if current route is in exception list
     */
    protected function isExcepted(Request $request): bool
    {
        $exceptRoutes = config('erp-agent.middleware.except', []);

        foreach ($exceptRoutes as $route) {
            if ($request->routeIs($route) || $request->is($route)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle invalid license response
     */
    protected function handleInvalidLicense(Request $request): Response
    {
        $status = $this->licenseService->getStatus();

        // API request
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'License invalid',
                'message' => 'Your license is not valid. Please contact support.',
                'grace_period' => $status['in_grace_period'] ?? false,
            ], 403);
        }

        // Web request - redirect
        $redirectTo = config('erp-agent.middleware.redirect_to', '/license-expired');

        return redirect($redirectTo)->with([
            'license_error' => true,
            'license_status' => $status,
        ]);
    }
}
