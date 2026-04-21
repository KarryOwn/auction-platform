<?php

namespace App\Services;

use App\Models\ReferralReward;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReferralService
{
    public function __construct(protected WalletService $walletService) {}

    /**
     * Link a new user to a referrer by referral code.
     * Call this inside RegisteredUserController::store() after user creation.
     */
    public function linkReferral(User $newUser, ?string $referralCode): void
    {
        if (! $referralCode) {
            return;
        }

        $referrer = User::where('referral_code', $referralCode)
            ->where('id', '!=', $newUser->id)
            ->first();

        if (! $referrer) {
            return;
        }

        $newUser->update(['referred_by_user_id' => $referrer->id]);

        ReferralReward::create([
            'referrer_id'      => $referrer->id,
            'referee_id'       => $newUser->id,
            'referrer_reward'  => (float) config('auction.referral.referrer_reward', 5.0),
            'referee_reward'   => (float) config('auction.referral.referee_reward', 2.5),
            'status'           => 'pending',
        ]);

        if (config('auction.referral.credit_on', 'registration') === 'registration') {
            $this->creditReward($newUser);
        }
    }

    /**
     * Credit both referrer and referee.
     */
    public function creditReward(User $referee): void
    {
        $reward = ReferralReward::where('referee_id', $referee->id)
            ->where('status', 'pending')
            ->first();

        if (! $reward) {
            return;
        }

        DB::transaction(function () use ($reward, $referee) {
            $referrer = $reward->referrer;

            $this->walletService->deposit(
                $referrer,
                (float) $reward->referrer_reward,
                "Referral bonus — {$referee->name} joined using your link",
            );

            $this->walletService->deposit(
                $referee,
                (float) $reward->referee_reward,
                'Welcome bonus — referral credit',
            );

            $reward->update([
                'status'      => 'credited',
                'credited_at' => now(),
            ]);
        });

        Log::info('ReferralService: rewards credited', [
            'referrer_id' => $reward->referrer_id,
            'referee_id'  => $referee->id,
        ]);
    }
}
