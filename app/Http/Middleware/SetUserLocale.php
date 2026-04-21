<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetUserLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->preferences && $user->preferences->locale) {
            $locale = $user->preferences->locale;
        } else {
            $locale = $request->getPreferredLanguage(config('app.supported_locales', ['en']));
        }

        App::setLocale($locale);
        
        return $next($request);
    }
}
