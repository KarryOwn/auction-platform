<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsStaff
{
    /**
     * Only allow admin users to proceed.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isStaff()) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }

            abort(403, 'You do not have permission to access this area.');
        }

        return $next($request);
    }
}
