<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeConnectController extends Controller
{
    /**
     * Create a Stripe Express account and redirect to onboarding.
     */
    public function onboard(Request $request)
    {
        $user = $request->user();

        // Reuse existing account if already created but not finished
        if (! $user->stripe_connect_account_id) {
            $account = \Stripe\Account::create([
                'type'  => 'express',
                'email' => $user->email,
                'metadata' => ['user_id' => $user->id],
                'capabilities' => [
                    'transfers' => ['requested' => true],
                ],
            ]);

            $user->update(['stripe_connect_account_id' => $account->id]);
        }

        $accountLink = \Stripe\AccountLink::create([
            'account'     => $user->stripe_connect_account_id,
            'refresh_url' => route('wallet.connect.onboard'),   // retry if expired
            'return_url'  => route('wallet.connect.return'),
            'type'        => 'account_onboarding',
        ]);

        return redirect($accountLink->url);
    }

    /**
     * User returns from Stripe onboarding — check if complete.
     */
    public function return(Request $request)
    {
        $user    = $request->user();
        $account = \Stripe\Account::retrieve($user->stripe_connect_account_id);

        // charges_enabled = onboarding complete + payouts enabled
        if ($account->charges_enabled) {
            $user->update(['stripe_connect_onboarded' => true]);

            return redirect()->route('user.wallet')
                ->with('success', 'Bank account connected! You can now withdraw funds.');
        }

        return redirect()->route('user.wallet')
            ->with('error', 'Onboarding incomplete. Please try again.');
    }

    /**
     * Disconnect — used if user wants to change bank account.
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();

        if (! $user->stripe_connect_account_id) {
            return redirect()->route('user.wallet');
        }

        // Link to Stripe Express dashboard so user can update bank details
        $loginLink = \Stripe\Account::createLoginLink(
            $user->stripe_connect_account_id
        );

        return redirect($loginLink->url);
    }
}
