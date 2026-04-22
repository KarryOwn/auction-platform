<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    public function edit(Request $request)
    {
        $preferences = $request->user()->getNotificationPreferences();
        $supportedCurrencies = config('auction.supported_currencies', ['USD']);

        return view('user.notification-preferences', compact('preferences', 'supportedCurrencies'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'preferences'                    => 'required|array',
            'preferences.*.email'            => 'boolean',
            'preferences.*.push'             => 'boolean',
            'preferences.*.database'         => 'boolean',
            'locale'                         => 'nullable|string|max:10',
            'display_currency'               => 'nullable|string|size:3|in:' . implode(',', config('auction.supported_currencies', ['USD'])),
        ]);

        // Merge submitted toggles with defaults (unchecked checkboxes won't be sent)
        $defaults = User::DEFAULT_NOTIFICATION_PREFERENCES;
        $submitted = $validated['preferences'];

        $merged = [];
        foreach ($defaults as $event => $channels) {
            foreach ($channels as $channel => $default) {
                $merged[$event][$channel] = isset($submitted[$event][$channel])
                    ? (bool) $submitted[$event][$channel]
                    : false;
            }
        }

        $request->user()->update(['notification_preferences' => $merged]);

        if (isset($validated['locale']) || isset($validated['display_currency'])) {
            $request->user()->userPreference()->updateOrCreate(
                ['user_id' => $request->user()->id],
                array_filter([
                    'locale' => $validated['locale'] ?? null,
                    'display_currency' => isset($validated['display_currency'])
                        ? strtoupper($validated['display_currency'])
                        : null,
                ], static fn ($value) => $value !== null)
            );
        }

        $cookieCurrency = strtoupper(
            $validated['display_currency']
                ?? $request->user()->userPreference?->display_currency
                ?? display_currency()
        );

        return redirect()->route('user.notification-preferences')
            ->withCookie(cookie('display_currency', $cookieCurrency, 60 * 24 * 365))
            ->with('success', 'Notification preferences updated.');
    }
}
