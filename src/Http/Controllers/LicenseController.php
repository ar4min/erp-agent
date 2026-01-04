<?php

namespace Ar4min\ErpAgent\Http\Controllers;

use Illuminate\Routing\Controller;
use Ar4min\ErpAgent\Services\LicenseService;

class LicenseController extends Controller
{
    /**
     * Show license expired page.
     */
    public function expired()
    {
        $licenseService = app(LicenseService::class);
        $status = $licenseService->getStatus();

        $graceRemaining = 0;

        if ($status['in_grace_period'] ?? false) {
            $graceRemaining = $status['grace_hours_remaining'] ?? 0;
        }

        return view('erp-agent::license-expired', [
            'graceRemaining' => $graceRemaining,
        ]);
    }
}
