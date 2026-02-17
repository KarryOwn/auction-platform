<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SellerApplication;
use App\Models\User;
use App\Notifications\SellerApplicationResultNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SellerApplicationController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status');

        $applications = SellerApplication::query()
            ->with(['user:id,name,email', 'reviewer:id,name'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.seller-applications.index', compact('applications', 'status'));
    }

    public function show(SellerApplication $application)
    {
        $application->load(['user', 'reviewer']);

        return view('admin.seller-applications.show', compact('application'));
    }

    public function approve(Request $request, SellerApplication $application): RedirectResponse
    {
        if ($application->status !== SellerApplication::STATUS_PENDING) {
            return back()->withErrors(['status' => 'Only pending applications can be approved.']);
        }

        DB::transaction(function () use ($request, $application) {
            $user = $application->user;

            $application->update([
                'status' => SellerApplication::STATUS_APPROVED,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);

            $user->update([
                'role' => User::ROLE_SELLER,
                'seller_verified_at' => now(),
                'seller_application_status' => 'approved',
                'seller_rejected_reason' => null,
                'seller_slug' => $user->seller_slug ?: $this->generateUniqueSlug($user->name),
            ]);

            $user->notify(new SellerApplicationResultNotification($application));

            AuditLog::record('seller.application_approved', 'seller_application', $application->id, [
                'reviewed_by' => $request->user()->id,
                'user_id' => $user->id,
            ], $request->user()->id);
        });

        return redirect()->route('admin.seller-applications.index')->with('status', 'Application approved.');
    }

    public function reject(Request $request, SellerApplication $application): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ]);

        if ($application->status !== SellerApplication::STATUS_PENDING) {
            return back()->withErrors(['status' => 'Only pending applications can be rejected.']);
        }

        DB::transaction(function () use ($request, $application, $validated) {
            $user = $application->user;

            $application->update([
                'status' => SellerApplication::STATUS_REJECTED,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'rejection_reason' => $validated['rejection_reason'],
            ]);

            $user->update([
                'seller_application_status' => 'rejected',
                'seller_rejected_reason' => $validated['rejection_reason'],
            ]);

            $user->notify(new SellerApplicationResultNotification($application));

            AuditLog::record('seller.application_rejected', 'seller_application', $application->id, [
                'reviewed_by' => $request->user()->id,
                'reason' => $validated['rejection_reason'],
                'user_id' => $user->id,
            ], $request->user()->id);
        });

        return redirect()->route('admin.seller-applications.index')->with('status', 'Application rejected.');
    }

    private function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'seller';
        $slug = $base;
        $counter = 1;

        while (User::where('seller_slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
