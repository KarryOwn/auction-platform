<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserBlockController extends Controller
{
    public function toggle(Request $request, User $user): JsonResponse|RedirectResponse
    {
        $blocker = $request->user();

        if ($blocker->id === $user->id) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Cannot block yourself.'], 422);
            }

            return back()->withErrors(['block' => 'Cannot block yourself.']);
        }

        if ($blocker->hasBlocked($user->id)) {
            $blocker->blockedUsers()->detach($user->id);
            if ($request->expectsJson()) {
                return response()->json(['blocked' => false, 'message' => 'User unblocked.']);
            }

            return back()->with('status', 'User unblocked.');
        }

        $blocker->blockedUsers()->attach($user->id);
        if ($request->expectsJson()) {
            return response()->json(['blocked' => true, 'message' => 'User blocked.']);
        }

        return back()->with('status', 'User blocked.');
    }
}
