<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\SavedSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavedSearchController extends Controller
{
    public function index(Request $request)
    {
        $searches = SavedSearch::where('user_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->paginate(20);

        return view('user.saved-searches', compact('searches'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:100',
            'query_params' => 'required|array',
        ]);

        // Limit to 20 saved searches per user
        $count = SavedSearch::where('user_id', $request->user()->id)->count();
        if ($count >= 20) {
            return response()->json([
                'message' => 'You can save up to 20 searches. Please delete one first.',
            ], 422);
        }

        $search = SavedSearch::create([
            'user_id'      => $request->user()->id,
            'name'         => $validated['name'],
            'query_params' => $validated['query_params'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Search saved!',
            'search'  => $search,
        ], 201);
    }

    public function destroy(Request $request, SavedSearch $savedSearch): JsonResponse
    {
        if ($savedSearch->user_id !== $request->user()->id) {
            abort(403);
        }

        $savedSearch->delete();

        return response()->json(['success' => true, 'message' => 'Search deleted.']);
    }
}
