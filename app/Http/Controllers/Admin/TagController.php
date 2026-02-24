<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Services\TagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TagController extends Controller
{
    public function __construct(
        private readonly TagService $tagService,
    ) {}

    public function index(Request $request)
    {
        $query = Tag::withCount('auctions');

        if ($search = $request->input('q')) {
            $query->where('name', 'ilike', "%{$search}%");
        }

        $tags = $query->orderBy('name')->paginate(25)->withQueryString();

        return view('admin.tags.index', compact('tags'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:50', Rule::unique('tags', 'name')],
            'type'  => ['nullable', 'string', 'max:30'],
            'color' => ['nullable', 'string', 'max:7', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        Tag::create($validated);
        $this->tagService->invalidateCache();

        return redirect()->route('admin.tags.index')
            ->with('status', "Tag \"{$validated['name']}\" created.");
    }

    public function update(Request $request, Tag $tag): RedirectResponse
    {
        $validated = $request->validate([
            'name'  => ['sometimes', 'string', 'max:50', Rule::unique('tags', 'name')->ignore($tag->id)],
            'type'  => ['nullable', 'string', 'max:30'],
            'color' => ['nullable', 'string', 'max:7', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $tag->update($validated);
        $this->tagService->invalidateCache();

        return redirect()->route('admin.tags.index')
            ->with('status', "Tag \"{$tag->name}\" updated.");
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        $name = $tag->name;
        $tag->auctions()->detach();
        $tag->delete();
        $this->tagService->invalidateCache();

        return redirect()->route('admin.tags.index')
            ->with('status', "Tag \"{$name}\" deleted.");
    }

    public function merge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_id'  => ['required', 'integer', 'exists:tags,id'],
            'source_ids' => ['required', 'array', 'min:1'],
            'source_ids.*' => ['integer', 'exists:tags,id'],
        ]);

        $target = Tag::findOrFail($validated['target_id']);
        $affected = $this->tagService->merge($target, $validated['source_ids']);

        return response()->json([
            'success' => true,
            'message' => "Merged {$affected} auction associations into \"{$target->name}\".",
        ]);
    }
}
