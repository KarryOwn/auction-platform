<?php

namespace App\Http\Controllers;

use App\Models\DataExportRequest;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'blockedUsers' => $request->user()->blockedUsers()->latest('user_blocks.created_at')->get(),
            'latestExportRequest' => DataExportRequest::query()
                ->where('user_id', $request->user()->id)
                ->latest()
                ->first(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Upload a new avatar for the user.
     */
    public function uploadAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
        ]);

        $request->user()
            ->addMediaFromRequest('avatar')
            ->toMediaCollection('avatar');

        return Redirect::route('profile.edit')->with('status', 'avatar-updated');
    }

    /**
     * Remove the user's avatar.
     */
    public function deleteAvatar(Request $request): RedirectResponse
    {
        $request->user()->clearMediaCollection('avatar');

        return Redirect::route('profile.edit')->with('status', 'avatar-removed');
    }

    public function deactivate(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // Optional: Call VacationModeService or handle open escrow holds here

        $user->is_deactivated = true;
        $user->deactivated_at = now();
        $user->reactivation_deadline = now()->addDays(30);
        $user->save();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('status', 'Account deactivated. You can reactivate within 30 days.');
    }

    public function showReactivate(Request $request): View|RedirectResponse
    {
        // Require auth before allowing reactivation
        if (! Auth::check() && ! session()->has('reactivation_user_id')) {
            return redirect()->route('login');
        }
        return view('profile.reactivate');
    }

    public function reactivate(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user || ! $user->is_deactivated) {
            return redirect()->route('dashboard');
        }

        if ($user->reactivation_deadline && now()->isAfter($user->reactivation_deadline)) {
            return redirect('/')->with('error', 'Reactivation period has expired. Account permanently deleted.');
        }

        $user->update([
            'is_deactivated'        => false,
            'deactivated_at'        => null,
            'reactivation_deadline' => null,
        ]);

        return redirect()->route('dashboard')->with('status', 'Account reactivated successfully.');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        // Anonymise user data instead of cascade delete to preserve bid/auction history integrity
        $user->update([
            'name'  => 'Deleted User #' . $user->id,
            'email' => 'deleted-' . $user->id . '@deleted.invalid',
        ]);
        
        $user->delete(); // SoftDelete — sets deleted_at

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
