<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsSeller
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if (! $user->isSeller() || ! $user->isVerifiedSeller()) {
            return redirect()->route('seller.apply.form')
                ->with('status', 'You must be an approved seller to access that page.');
        }

        return $next($request);
    }
}
