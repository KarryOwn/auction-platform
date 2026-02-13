<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsAdmin
{
    /**
     * Only allow admin users to proceed (stricter than EnsureIsStaff).
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isAdmin()) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }

            abort(403, 'Only administrators can perform this action.');
        }

        return $next($request);
    }
}
