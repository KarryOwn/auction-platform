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

        $referralCount = $user->referrals()->count();
        $creditedCount = ReferralReward::where('referrer_id', $user->id)
            ->where('status', 'credited')
            ->count();
        $earnedThisMonth = ReferralReward::where('referrer_id', $user->id)
            ->where('status', 'credited')
            ->where('credited_at', '>=', now()->startOfMonth())
            ->sum('referrer_reward');
        $conversionRate = $referralCount > 0
            ? round(($creditedCount / $referralCount) * 100)
            : 0;
        $nextMilestone = max(0, 5 - $creditedCount);

        return view('user.referrals.index', [
            'referrals' => $referrals,
            'totalEarned' => $totalEarned,
            'pendingEarned' => $pendingEarned,
            'referralCount' => $referralCount,
            'creditedCount' => $creditedCount,
            'earnedThisMonth' => $earnedThisMonth,
            'conversionRate' => $conversionRate,
            'nextMilestone' => $nextMilestone,
            'referralLink' => route('register', ['ref' => $user->referral_code]),
        ]);
    }
}
