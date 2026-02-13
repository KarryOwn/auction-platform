<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::with('user:id,name,email');

        if ($action = $request->input('action')) {
            $query->where('action', $action);
        }

        if ($targetType = $request->input('target_type')) {
            $query->where('target_type', $targetType);
        }

        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }

        $logs = $query->orderByDesc('created_at')->paginate(50)->withQueryString();

        $actions = AuditLog::select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        if ($request->wantsJson()) {
            return response()->json($logs);
        }

        return view('admin.audit-logs.index', compact('logs', 'actions'));
    }
}
