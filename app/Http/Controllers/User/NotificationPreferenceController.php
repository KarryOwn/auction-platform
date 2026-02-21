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

        return view('user.notification-preferences', compact('preferences'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'preferences'                    => 'required|array',
            'preferences.*.email'            => 'boolean',
            'preferences.*.push'             => 'boolean',
            'preferences.*.database'         => 'boolean',
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

        return redirect()->route('user.notification-preferences')
            ->with('success', 'Notification preferences updated.');
    }
}
