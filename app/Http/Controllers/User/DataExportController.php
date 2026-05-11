<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
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

        if ($existing && $existing->status === 'pending') {
            return back()->with('status', 'Your export request is waiting for admin approval.');
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

        return back()->with('status', "Data export requested. You'll be notified after an admin approves it.");
    }

    public function download(Request $request, DataExportRequest $exportRequest): BinaryFileResponse
    {
        abort_unless($exportRequest->user_id === $request->user()->id, 403);
        abort_unless($exportRequest->status === 'ready', 404);

        $filePath = $this->resolveExportPath($exportRequest);
        abort_unless($filePath, 404);

        if ($exportRequest->expires_at && now()->isAfter($exportRequest->expires_at)) {
            $exportRequest->update(['status' => 'expired']);
            Storage::delete($filePath);
            abort(404, 'Export link expired.');
        }

        return response()->download(Storage::path($filePath));
    }

    private function resolveExportPath(DataExportRequest $exportRequest): ?string
    {
        if (! $exportRequest->file_path) {
            return null;
        }

        if (Storage::exists($exportRequest->file_path)) {
            return $exportRequest->file_path;
        }

        $legacyPath = preg_replace('#^private/#', '', $exportRequest->file_path);

        if ($legacyPath && $legacyPath !== $exportRequest->file_path && Storage::exists($legacyPath)) {
            $exportRequest->update(['file_path' => $legacyPath]);

            return $legacyPath;
        }

        return null;
    }
}
