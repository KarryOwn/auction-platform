<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MaintenanceWindow;
use App\Services\MaintenanceWindowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MaintenanceController extends Controller
{
    public function __construct(
        private readonly MaintenanceWindowService $maintenanceWindowService,
    ) {}

    public function index(): View
    {
        $windows = MaintenanceWindow::query()
            ->with('creator:id,name')
            ->orderByDesc('scheduled_start')
            ->get();

        return view('admin.maintenance.index', [
            'windows' => $windows,
            'bypassUrl' => $this->maintenanceWindowService->getBypassUrl(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateWindow($request);

        $window = MaintenanceWindow::create([
            ...$validated,
            'status' => MaintenanceWindow::STATUS_SCHEDULED,
            'created_by' => $request->user()->id,
        ]);

        $this->maintenanceWindowService->forgetUpcomingCache();
        AuditLog::record('maintenance.created', 'maintenance_window', $window->id, [
            'scheduled_start' => $window->scheduled_start?->toIso8601String(),
            'scheduled_end' => $window->scheduled_end?->toIso8601String(),
        ]);

        return redirect()->route('admin.maintenance.index')
            ->with('success', 'Maintenance window scheduled.');
    }

    public function update(Request $request, MaintenanceWindow $window): RedirectResponse
    {
        $validated = $this->validateWindow($request, $window);

        $window->update($validated);
        $this->maintenanceWindowService->forgetUpcomingCache();

        AuditLog::record('maintenance.updated', 'maintenance_window', $window->id, [
            'scheduled_start' => $window->scheduled_start?->toIso8601String(),
            'scheduled_end' => $window->scheduled_end?->toIso8601String(),
            'status' => $window->status,
        ]);

        return redirect()->route('admin.maintenance.index')
            ->with('success', 'Maintenance window updated.');
    }

    public function cancel(MaintenanceWindow $window): RedirectResponse
    {
        $this->maintenanceWindowService->cancel($window);

        AuditLog::record('maintenance.cancelled', 'maintenance_window', $window->id, [
            'status' => $window->fresh()->status,
        ]);

        return redirect()->route('admin.maintenance.index')
            ->with('success', 'Maintenance window cancelled.');
    }

    private function validateWindow(Request $request, ?MaintenanceWindow $window = null): array
    {
        return $request->validate([
            'scheduled_start' => ['required', 'date', 'after:' . now()->subMinute()->toDateTimeString()],
            'scheduled_end' => ['required', 'date', 'after:scheduled_start'],
            'message' => ['required', 'string', 'max:500'],
            'status' => ['sometimes', 'string', Rule::in([
                MaintenanceWindow::STATUS_SCHEDULED,
                MaintenanceWindow::STATUS_ACTIVE,
                MaintenanceWindow::STATUS_COMPLETED,
                MaintenanceWindow::STATUS_CANCELLED,
            ])],
        ]);
    }
}
