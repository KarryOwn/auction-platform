<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AuditLog;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = AuditLog::where('user_id', $request->user()->id)
            ->whereNotIn('action', [
                'auction.force_cancelled',
                'auction.extended',
            ])
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('user.activity', compact('logs'));
    }
}
