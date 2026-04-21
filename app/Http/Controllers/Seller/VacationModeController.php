<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Services\VacationModeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VacationModeController extends Controller
{
    public function __construct(protected VacationModeService $service) {}

    public function activate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ends_at' => ['nullable', 'date', 'after:now'],
            'message' => ['nullable', 'string', 'max:500'],
            'mode'    => ['nullable', Rule::in(['pause', 'message_only'])],
        ]);

        $this->service->activate(
            $request->user(),
            isset($validated['ends_at']) ? \Carbon\Carbon::parse($validated['ends_at']) : null,
            $validated['message'] ?? '',
            $validated['mode'] ?? 'pause',
        );

        return redirect()->route('seller.dashboard')
            ->with('status', 'Vacation mode activated. Your active auctions have been paused.');
    }

    public function deactivate(Request $request): RedirectResponse
    {
        $this->service->deactivate($request->user());

        return redirect()->route('seller.dashboard')
            ->with('status', 'Vacation mode deactivated. Your auctions have been resumed.');
    }
}
