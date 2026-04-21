<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotBanned
{
    /**
     * Reject banned users and force-logout them.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isBanned()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->wantsJson()) {
                return response()->json(['message' => 'Your account has been banned.'], 403);
            }

            return redirect()->route('login')
                ->with('status', 'Your account has been banned. Contact support if you believe this is a mistake.');
        }

        if ($request->user()?->is_deactivated) {
            $userId = $request->user()->id;
            Auth::guard('web')->logout();
            
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            
            $request->session()->put('reactivation_user_id', $userId);

            return redirect()->route('account.reactivate')
                ->with('status', 'Your account is deactivated. Would you like to reactivate it?');
        }

        return $next($request);
    }
}
