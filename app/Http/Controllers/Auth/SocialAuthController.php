<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    protected array $allowedProviders = ['google', 'github'];

    /**
     * Redirect the user to the social provider's OAuth page.
     */
    public function redirect(string $provider): RedirectResponse
    {
        if (! in_array($provider, $this->allowedProviders)) {
            abort(404);
        }

        return Socialite::driver($provider)->redirect();
    }

    /**
     * Handle the callback from the social provider.
     */
    public function callback(string $provider): RedirectResponse
    {
        if (! in_array($provider, $this->allowedProviders)) {
            abort(404);
        }

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Throwable $e) {
            Log::warning("Social login failed for {$provider}", [
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('login')
                ->with('status', "Unable to login with {$provider}. Please try again.");
        }

        $providerIdColumn = "{$provider}_id";

        // 1. Find existing user by provider ID
        $user = User::where($providerIdColumn, $socialUser->getId())->first();

        if ($user) {
            Auth::login($user, remember: true);

            return redirect()->intended(route('dashboard'));
        }

        // 2. Find existing user by email — link the social account
        if ($socialUser->getEmail()) {
            $user = User::where('email', $socialUser->getEmail())->first();

            if ($user) {
                $user->update([$providerIdColumn => $socialUser->getId()]);

                Auth::login($user, remember: true);

                AuditLog::record('social_login.linked', 'user', $user->id, [
                    'provider' => $provider,
                ]);

                return redirect()->intended(route('dashboard'));
            }
        }

        // 3. Create a new user (no password)
        $user = User::create([
            'name'              => $socialUser->getName() ?? $socialUser->getNickname() ?? 'User',
            'email'             => $socialUser->getEmail(),
            $providerIdColumn   => $socialUser->getId(),
            'email_verified_at' => now(),
        ]);

        Auth::login($user, remember: true);

        AuditLog::record('social_login.registered', 'user', $user->id, [
            'provider' => $provider,
        ]);

        return redirect()->route('dashboard');
    }
}
