<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Support\ApiAbilities;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenController extends Controller
{
    public function index(Request $request): View
    {
        $tokens = $request->user()->tokens()->latest()->get();

        return view('user.api-tokens.index', [
            'tokens' => $tokens,
            'abilities' => ApiAbilities::ALL,
            'defaultAbilities' => ApiAbilities::DEFAULT_ABILITIES,
            'newToken' => session('new_api_token'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string', Rule::in(ApiAbilities::ALL)],
        ]);

        $abilities = $validated['abilities'] ?? ApiAbilities::DEFAULT_ABILITIES;
        $token = $request->user()->createToken($validated['name'], $abilities);

        return redirect()
            ->route('user.api-tokens.index')
            ->with('success', 'API token created. Copy it now; it will not be shown again.')
            ->with('new_api_token', [
                'plain_text' => $token->plainTextToken,
                'name' => $validated['name'],
                'abilities' => $abilities,
            ]);
    }

    public function destroy(Request $request, PersonalAccessToken $token): RedirectResponse
    {
        abort_unless((int) $token->tokenable_id === (int) $request->user()->id, 403);
        abort_unless($token->tokenable_type === get_class($request->user()), 403);

        $token->delete();

        return redirect()
            ->route('user.api-tokens.index')
            ->with('success', 'API token revoked.');
    }
}
