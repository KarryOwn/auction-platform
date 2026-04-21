<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerFollowController extends Controller
{
    public function toggle(Request $request, User $seller): JsonResponse
    {
        $user = $request->user();

        if ($user->id === $seller->id) {
            return response()->json(['error' => 'Cannot follow yourself.'], 422);
        }

        if (! $seller->isVerifiedSeller()) {
            return response()->json(['error' => 'User is not a verified seller.'], 422);
        }

        $existing = $user->following()->where('seller_id', $seller->id)->first();

        if ($existing) {
            $user->following()->detach($seller->id);
            return response()->json(['following' => false, 'message' => 'Unfollowed seller.']);
        }

        $user->following()->attach($seller->id, ['notify_new_listings' => true]);
        return response()->json(['following' => true, 'message' => 'Now following seller.']);
    }
}
