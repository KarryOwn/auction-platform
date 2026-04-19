<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\KeywordAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KeywordAlertController extends Controller
{
    public function index(Request $request)
    {
        $alerts = KeywordAlert::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return view('user.keyword-alerts', compact('alerts'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'keyword' => [
                'required',
                'string',
                'max:100',
                Rule::unique('keyword_alerts')->where(fn ($query) => $query->where('user_id', $request->user()->id)),
            ],
        ]);

        KeywordAlert::create([
            'user_id' => $request->user()->id,
            'keyword' => $request->keyword,
        ]);

        return redirect()->back()->with('success', 'Keyword alert created.');
    }

    public function destroy(Request $request, KeywordAlert $alert)
    {
        if ($request->user()->id !== $alert->user_id) {
            abort(403, 'Unauthorized.');
        }

        $alert->delete();

        return redirect()->back()->with('success', 'Keyword alert removed.');
    }

    public function toggle(Request $request, KeywordAlert $alert): JsonResponse
    {
        if ($request->user()->id !== $alert->user_id) {
            abort(403, 'Unauthorized.');
        }

        $alert->update([
            'is_active' => !$alert->is_active,
        ]);

        return response()->json(['active' => $alert->is_active]);
    }
}
