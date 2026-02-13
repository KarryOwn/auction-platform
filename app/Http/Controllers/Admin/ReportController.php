<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ReportedAuction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * List reported auctions.
     */
    public function index(Request $request)
    {
        $query = ReportedAuction::with([
            'auction:id,title,status,current_price',
            'reporter:id,name,email',
            'reviewer:id,name',
        ]);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $statusCounts = [
            'all'       => ReportedAuction::count(),
            'pending'   => ReportedAuction::where('status', 'pending')->count(),
            'reviewed'  => ReportedAuction::where('status', 'reviewed')->count(),
            'actioned'  => ReportedAuction::where('status', 'actioned')->count(),
            'dismissed' => ReportedAuction::where('status', 'dismissed')->count(),
        ];

        $reports = $query->orderByDesc('created_at')->paginate(25)->withQueryString();

        if ($request->wantsJson()) {
            return response()->json($reports);
        }

        return view('admin.reports.index', compact('reports', 'statusCounts'));
    }

    /**
     * Review a report (AJAX).
     */
    public function review(Request $request, ReportedAuction $report): JsonResponse
    {
        $request->validate([
            'status'      => 'required|in:reviewed,dismissed,actioned',
            'admin_notes' => 'sometimes|string|max:1000',
        ]);

        $report->update([
            'status'      => $request->input('status'),
            'admin_notes' => $request->input('admin_notes'),
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        AuditLog::record(
            action: 'report.reviewed',
            targetType: 'report',
            targetId: $report->id,
            metadata: [
                'auction_id' => $report->auction_id,
                'status'     => $request->input('status'),
                'notes'      => $request->input('admin_notes'),
            ],
        );

        return response()->json([
            'message' => "Report #{$report->id} marked as {$request->input('status')}.",
            'report'  => $report->fresh()->load(['auction', 'reporter', 'reviewer']),
        ]);
    }
}
