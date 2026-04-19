<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Dispute;
use App\Notifications\DisputeStatusUpdatedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DisputeController extends Controller
{
    public function index(Request $request)
    {
        $query = Dispute::query()
            ->with([
                'auction:id,title,status,current_price',
                'claimant:id,name,email',
                'respondent:id,name,email',
                'resolver:id,name',
            ]);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        $statusCounts = [
            'all' => Dispute::count(),
            'open' => Dispute::open()->count(),
            'under_review' => Dispute::underReview()->count(),
            'resolved_buyer' => Dispute::where('status', Dispute::STATUS_RESOLVED_BUYER)->count(),
            'resolved_seller' => Dispute::where('status', Dispute::STATUS_RESOLVED_SELLER)->count(),
            'closed' => Dispute::where('status', Dispute::STATUS_CLOSED)->count(),
        ];

        $disputes = $query->latest('created_at')->paginate(25)->withQueryString();

        return view('admin.disputes.index', [
            'disputes' => $disputes,
            'statusCounts' => $statusCounts,
            'selectedType' => $request->input('type'),
        ]);
    }

    public function show(Dispute $dispute)
    {
        $dispute->load([
            'auction:id,title,status,current_price,winner_id,user_id',
            'claimant:id,name,email',
            'respondent:id,name,email',
            'resolver:id,name,email',
        ]);

        return view('admin.disputes.show', compact('dispute'));
    }

    public function update(Request $request, Dispute $dispute): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([
                Dispute::STATUS_OPEN,
                Dispute::STATUS_UNDER_REVIEW,
                Dispute::STATUS_RESOLVED_BUYER,
                Dispute::STATUS_RESOLVED_SELLER,
                Dispute::STATUS_CLOSED,
            ])],
            'resolution_notes' => ['required', 'string', 'max:4000'],
        ]);

        $dispute->update([
            'status' => $validated['status'],
            'resolution_notes' => $validated['resolution_notes'],
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
        ]);

        $dispute->load(['claimant', 'respondent', 'auction']);

        if ($dispute->claimant) {
            $dispute->claimant->notify(new DisputeStatusUpdatedNotification($dispute));
        }

        if ($dispute->respondent && (int) $dispute->respondent_id !== (int) $dispute->claimant_id) {
            $dispute->respondent->notify(new DisputeStatusUpdatedNotification($dispute));
        }

        AuditLog::record(
            action: 'dispute.resolved',
            targetType: 'dispute',
            targetId: $dispute->id,
            metadata: [
                'auction_id' => $dispute->auction_id,
                'status' => $dispute->status,
                'resolution_notes' => $dispute->resolution_notes,
                'resolved_by' => auth()->id(),
            ],
        );

        return redirect()
            ->route('admin.disputes.show', $dispute)
            ->with('success', 'Dispute updated and both parties notified.');
    }
}
