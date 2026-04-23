<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    /**
     * @var array<string, array{0: string, 1: string}>
     */
    private const EVENT_LABELS = [
        'outbid' => ['Outbid Alert', 'When someone places a higher bid on an auction you bid on'],
        'auction_won' => ['Auction Won', 'When you win an auction'],
        'auction_lost' => ['Auction Lost', 'When an auction you bid on closes with another winner'],
        'auction_ending' => ['Auction Ending Soon', 'When a watched auction is about to end'],
        'wallet' => ['Wallet Updates', 'Deposits, payments, and balance changes'],
        'marketing' => ['Promotions & News', 'Featured auctions, platform news, and special offers'],
    ];

    public function edit(Request $request)
    {
        $preferences = $request->user()->getNotificationPreferences();
        $supportedCurrencies = config('auction.supported_currencies', ['USD']);
        $eventLabels = self::EVENT_LABELS;

        return view('user.notification-preferences', compact('preferences', 'supportedCurrencies', 'eventLabels'));
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
            'default_outbid_threshold'       => 'nullable|numeric|min:0.01',
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
        $request->user()->update([
            'default_outbid_threshold' => $validated['default_outbid_threshold'] ?? null,
        ]);

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
