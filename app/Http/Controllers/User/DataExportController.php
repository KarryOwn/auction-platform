<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateUserDataExport;
use App\Models\DataExportRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

class DataExportController extends Controller
{
    public function requestExport(Request $request): RedirectResponse
    {
        $existing = DataExportRequest::where('user_id', $request->user()->id)
            ->whereIn('status', ['pending', 'processing', 'ready'])
            ->first();

        if ($existing && $existing->status === 'ready') {
            return redirect()->route('user.data-export.download', $existing);
        }

        if ($existing) {
            return back()->with('status', 'Your export is being prepared. Check back soon.');
        }

        // Check rate limiting: max 1 per 24 hours
        $recent = DataExportRequest::where('user_id', $request->user()->id)
            ->where('created_at', '>=', now()->subDay())
            ->exists();

        if ($recent) {
            return back()->with('error', 'You can only request one data export every 24 hours.');
        }

        $exportRequest = DataExportRequest::create([
            'user_id' => $request->user()->id,
            'status'  => 'pending',
        ]);

        GenerateUserDataExport::dispatch($exportRequest->id)->onQueue('default');

        return back()->with('status', "Data export requested. You'll be notified when it's ready.");
    }

    public function download(Request $request, DataExportRequest $exportRequest): BinaryFileResponse
    {
        abort_unless($exportRequest->user_id === $request->user()->id, 403);
        abort_unless($exportRequest->status === 'ready', 404);
        abort_unless(Storage::exists($exportRequest->file_path), 404);

        if ($exportRequest->expires_at && now()->isAfter($exportRequest->expires_at)) {
            $exportRequest->update(['status' => 'expired']);
            Storage::delete($exportRequest->file_path);
            abort(404, 'Export link expired.');
        }

        return response()->download(Storage::path($exportRequest->file_path));
    }
}
