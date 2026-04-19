<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserProfileController extends Controller
{
    /**
     * Display a user's public profile.
     */
    public function show(User $user)
    {
        // Don't show banned users
        if ($user->isBanned()) {
            abort(404);
        }

        $totalWins = $user->wonAuctions()->count();
        $memberSince = $user->created_at;
        $averageRating = $user->average_rating;
        $ratingCount = $user->rating_count;

        return view('profile.show', compact('user', 'totalWins', 'memberSince', 'averageRating', 'ratingCount'));
    }
}
