<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateUserDataExport;
use App\Models\AuditLog;
use App\Models\DataExportRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\RedirectResponse;

class DataExportRequestController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();

        $query = DataExportRequest::query()
            ->with('user:id,name,email')
            ->latest();

        if ($status !== '') {
            $query->where('status', $status);
        } else {
            $query->where('status', 'pending');
        }

        $exportRequests = $query->paginate(20)->withQueryString();

        return view('admin.data-exports.index', compact('exportRequests', 'status'));
    }

    public function approve(DataExportRequest $exportRequest): RedirectResponse
    {
        if ($exportRequest->status !== 'pending') {
            return back()->with('error', 'Only pending data export requests can be approved.');
        }

        $exportRequest->update(['status' => 'processing']);

        GenerateUserDataExport::dispatch($exportRequest->id)->onQueue('default');

        AuditLog::record('data_export.approved', 'data_export_request', $exportRequest->id, [
            'user_id' => $exportRequest->user_id,
        ]);

        return back()->with('status', 'Data export request approved. The export is being generated.');
    }
}
