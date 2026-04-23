<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PayoutSettingsController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'payout_schedule' => ['required', Rule::in(['manual', 'daily', 'weekly', 'monthly'])],
            'payout_schedule_day' => ['nullable', 'string', 'max:20'],
        ]);

        $day = match ($validated['payout_schedule']) {
            'weekly' => $validated['payout_schedule_day'] ?: 'monday',
            'monthly' => $validated['payout_schedule_day'] ?: '1',
            default => null,
        };

        $request->user()->update([
            'payout_schedule' => $validated['payout_schedule'],
            'payout_schedule_day' => $day,
        ]);

        return redirect()
            ->route('user.wallet')
            ->with('success', 'Payout schedule updated.');
    }
}
