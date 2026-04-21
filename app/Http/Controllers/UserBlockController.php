<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserBlockController extends Controller
{
    public function toggle(Request $request, User $user): JsonResponse
    {
        $blocker = $request->user();

        if ($blocker->id === $user->id) {
            return response()->json(['error' => 'Cannot block yourself.'], 422);
        }

        if ($blocker->hasBlocked($user->id)) {
            $blocker->blockedUsers()->detach($user->id);
            return response()->json(['blocked' => false, 'message' => 'User unblocked.']);
        }

        $blocker->blockedUsers()->attach($user->id);
        return response()->json(['blocked' => true, 'message' => 'User blocked.']);
    }
}
