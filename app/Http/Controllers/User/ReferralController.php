<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ReferralReward;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $referrals = $user->referrals()->with('referralReward')->paginate(10);

        $totalEarned = ReferralReward::where('referrer_id', $user->id)
            ->where('status', 'credited')
            ->sum('referrer_reward');

        $pendingEarned = ReferralReward::where('referrer_id', $user->id)
            ->where('status', 'pending')
            ->sum('referrer_reward');

        return view('user.referrals.index', [
            'referrals' => $referrals,
            'totalEarned' => $totalEarned,
            'pendingEarned' => $pendingEarned,
            'referralLink' => route('register', ['ref' => $user->referral_code]),
        ]);
    }
}
