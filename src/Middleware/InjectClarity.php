<?php

namespace Ar4min\ErpAgent\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class InjectClarity
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $response = $next($request);

        // Only inject into HTML responses
        if (!$this->shouldInject($request, $response)) {
            return $response;
        }

        $content = $response->getContent();
        $script = $this->buildClarityScript($request);

        // Inject before </head> or at the beginning of <body>
        if (str_contains($content, '</head>')) {
            $content = str_replace('</head>', $script . '</head>', $content);
        } elseif (str_contains($content, '<body')) {
            $content = preg_replace('/(<body[^>]*>)/i', '$1' . $script, $content);
        }

        $response->setContent($content);

        return $response;
    }

    /**
     * Determine if Clarity should be injected.
     */
    protected function shouldInject(Request $request, $response): bool
    {
        // Check if enabled
        if (!config('erp-agent.clarity.enabled', false)) {
            return false;
        }

        // Check if project ID is set
        if (empty(config('erp-agent.clarity.project_id'))) {
            return false;
        }

        // Check if auto-inject is enabled
        if (!config('erp-agent.clarity.auto_inject', true)) {
            return false;
        }

        // Only inject into successful HTML responses
        if (!$response instanceof Response) {
            return false;
        }

        $contentType = $response->headers->get('Content-Type', '');
        if (!str_contains($contentType, 'text/html') && !empty($contentType)) {
            // Check if no content type but has HTML content
            $content = $response->getContent();
            if (!str_contains($content, '<html') && !str_contains($content, '<!DOCTYPE')) {
                return false;
            }
        }

        // Check excluded routes
        $excludedRoutes = config('erp-agent.clarity.exclude_routes', []);
        foreach ($excludedRoutes as $route) {
            if ($request->is($route)) {
                return false;
            }
        }

        // Check excluded IPs
        $excludedIps = config('erp-agent.clarity.exclude_ips', []);
        if (in_array($request->ip(), $excludedIps)) {
            return false;
        }

        // Don't inject into AJAX requests
        if ($request->ajax()) {
            return false;
        }

        return true;
    }

    /**
     * Build the Clarity script with tenant tracking.
     */
    protected function buildClarityScript(Request $request): string
    {
        $projectId = config('erp-agent.clarity.project_id');
        $trackTenant = config('erp-agent.clarity.track_tenant', true);

        $script = <<<HTML
<!-- Microsoft Clarity - Injected by ERP Agent -->
<script type="text/javascript">
    (function(c,l,a,r,i,t,y){
        c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
        t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
        y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
    })(window, document, "clarity", "script", "{$projectId}");
HTML;

        // Add tenant/instance tracking
        if ($trackTenant) {
            $instanceId = config('erp-agent.instance.id', 'unknown');
            $tenantName = config('app.name', 'ERP Instance');

            // Get current user info if available
            $userId = 'anonymous';
            $userEmail = '';

            if (auth()->check()) {
                $user = auth()->user();
                $userId = $user->id ?? 'unknown';
                $userEmail = $user->email ?? '';
            }

            $script .= <<<HTML

    // Track tenant and user info
    window.clarity = window.clarity || function(){(window.clarity.q=window.clarity.q||[]).push(arguments)};
    clarity("set", "instance_id", "{$instanceId}");
    clarity("set", "tenant_name", "{$tenantName}");
    clarity("set", "user_id", "{$userId}");
HTML;

            if (!empty($userEmail)) {
                // Hash email for privacy
                $hashedEmail = substr(md5($userEmail), 0, 8);
                $script .= <<<HTML

    clarity("set", "user_hash", "{$hashedEmail}");
HTML;
            }
        }

        $script .= <<<HTML

</script>
<!-- End Microsoft Clarity -->
HTML;

        return $script;
    }
}
