<?php

namespace App\Http\Middleware;

use App\Services\MaintenanceWindowService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MaintenanceAnnouncement
{
    public function __construct(
        private readonly MaintenanceWindowService $maintenanceWindowService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $upcoming = $this->maintenanceWindowService->getUpcoming();

        if ($upcoming) {
            view()->share('maintenance_window', $upcoming);
        }

        view()->share('maintenance_bypass_url', $this->maintenanceWindowService->getBypassUrl());

        return $next($request);
    }
}
