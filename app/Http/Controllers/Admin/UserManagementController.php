<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    /**
     * List users with search and filters.
     */
    public function index(Request $request)
    {
        $query = User::query()->withCount(['auctions', 'bids']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        if ($role = $request->input('role')) {
            $query->where('role', $role);
        }

        if ($request->boolean('banned_only')) {
            $query->where('is_banned', true);
        }

        $users = $query->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        if ($request->wantsJson()) {
            return response()->json($users);
        }

        return view('admin.users.index', compact('users'));
    }

    /**
     * Detailed user profile for admin review.
     */
    public function show(Request $request, User $user)
    {
        $user->loadCount(['auctions', 'bids']);

        $buyerAnalytics = $this->buildBuyerAnalytics($user);

        $activity = [
            'total_bids'          => $user->bids()->count(),
            'bids_today'          => $user->bids()->whereDate('created_at', today())->count(),
            'auctions_created'    => $user->auctions()->count(),
            'active_auctions'     => $user->auctions()->where('status', 'active')->count(),
            'last_activity'       => $user->bids()->max('created_at'),
            'unique_ips'          => $user->bids()->distinct('ip_address')->count('ip_address'),
        ];

        $recentBids = $user->bids()
            ->with('auction:id,title,status')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['id', 'auction_id', 'amount', 'ip_address', 'created_at']);

        $auditHistory = AuditLog::where('target_type', 'user')
            ->where('target_id', $user->id)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        if ($request->wantsJson()) {
            return response()->json([
                'user'          => $user,
                'activity'      => $activity,
                'buyer_analytics' => $buyerAnalytics,
                'recent_bids'   => $recentBids,
                'audit_history' => $auditHistory,
            ]);
        }

        return view('admin.users.show', compact('user', 'activity', 'buyerAnalytics', 'recentBids', 'auditHistory'));
    }

    private function buildBuyerAnalytics(User $user, int $days = 30): array
    {
        $from = now()->subDays($days);
        $bids = $user->bids()->where('created_at', '>=', $from);
        $won = $user->wonAuctions()->where('closed_at', '>=', $from);

        $auctionsBidOn = (clone $bids)->distinct('auction_id')->count('auction_id');
        $auctionsWon = (clone $won)->count();

        return [
            'days' => $days,
            'total_bids' => (clone $bids)->count(),
            'auctions_bid_on' => $auctionsBidOn,
            'auctions_won' => $auctionsWon,
            'total_spent' => (float) (clone $won)->sum('winning_bid_amount'),
            'avg_bid_amount' => (float) ((clone $bids)->avg('amount') ?? 0),
            'win_rate_pct' => $auctionsBidOn > 0 ? round(($auctionsWon / $auctionsBidOn) * 100, 1) : 0,
            'wallet_balance' => (float) $user->wallet_balance,
        ];
    }

    /**
     * Ban a user.
     */
    public function ban(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        if ($user->isAdmin()) {
            return response()->json(['message' => 'Cannot ban an admin.'], 403);
        }

        if ($user->isBanned()) {
            return response()->json(['message' => 'User is already banned.'], 422);
        }

        $user->update([
            'is_banned'  => true,
            'banned_at'  => now(),
            'ban_reason' => $request->input('reason'),
        ]);

        $user->tokens()->delete();

        AuditLog::record(
            action: 'user.banned',
            targetType: 'user',
            targetId: $user->id,
            metadata: [
                'reason'     => $request->input('reason'),
                'user_email' => $user->email,
            ],
        );

        return response()->json([
            'message' => "User #{$user->id} ({$user->email}) has been banned.",
        ]);
    }

    /**
     * Unban a user.
     */
    public function unban(Request $request, User $user): JsonResponse
    {
        if (!$user->isBanned()) {
            return response()->json(['message' => 'User is not banned.'], 422);
        }

        $user->update([
            'is_banned'  => false,
            'banned_at'  => null,
            'ban_reason' => null,
        ]);

        AuditLog::record(
            action: 'user.unbanned',
            targetType: 'user',
            targetId: $user->id,
            metadata: [
                'reason'     => $request->input('reason', 'No reason provided'),
                'user_email' => $user->email,
            ],
        );

        return response()->json([
            'message' => "User #{$user->id} ({$user->email}) has been unbanned.",
        ]);
    }

    /**
     * Change a user's role.
     */
    public function changeRole(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'role' => 'required|in:user,seller,moderator,admin',
        ]);

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Cannot change your own role.'], 403);
        }

        $oldRole = $user->role;
        $user->update(['role' => $request->input('role')]);

        AuditLog::record(
            action: 'user.role_changed',
            targetType: 'user',
            targetId: $user->id,
            metadata: [
                'old_role'   => $oldRole,
                'new_role'   => $request->input('role'),
                'user_email' => $user->email,
            ],
        );

        return response()->json([
            'message' => "User #{$user->id} role changed from {$oldRole} to {$request->input('role')}.",
        ]);
    }
}
