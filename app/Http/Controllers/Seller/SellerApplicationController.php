<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\SellerApplicationRequest;
use App\Models\AuditLog;
use App\Models\SellerApplication;
use App\Models\User;
use App\Notifications\SellerApplicationSubmittedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SellerApplicationController extends Controller
{
    public function showForm(Request $request)
    {
        $user = $request->user();

        if ($user->isStaff()) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->isVerifiedSeller()) {
            return redirect()->route('seller.dashboard');
        }

        return view('seller.apply', ['user' => $user]);
    }

    public function apply(SellerApplicationRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->isStaff()) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->isVerifiedSeller() || $user->hasPendingSellerApplication()) {
            return redirect()->route('seller.application.status');
        }

        $application = SellerApplication::create([
            'user_id' => $user->id,
            'reason' => $request->string('reason')->toString(),
            'experience' => $request->string('experience')->toString() ?: null,
            'status' => SellerApplication::STATUS_PENDING,
        ]);

        $user->update([
            'seller_application_status' => 'pending',
            'seller_applied_at' => now(),
            'seller_application_note' => $request->string('reason')->toString(),
            'seller_rejected_reason' => null,
        ]);

        User::query()->whereIn('role', [User::ROLE_ADMIN, User::ROLE_MODERATOR])->get()->each(function (User $staff) use ($application) {
            $staff->notify(new SellerApplicationSubmittedNotification($application->load('user')));
        });

        AuditLog::record('seller.application_submitted', 'seller_application', $application->id, [
            'user_id' => $user->id,
        ], $user->id);

        return redirect()->route('seller.application.status')->with('status', 'Application submitted successfully.');
    }

    public function status(Request $request)
    {
        if ($request->user()->isStaff()) {
            return redirect()->route('admin.dashboard');
        }

        $application = SellerApplication::query()
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->first();

        return view('seller.application-status', [
            'application' => $application,
            'user' => $request->user(),
        ]);
    }
}
