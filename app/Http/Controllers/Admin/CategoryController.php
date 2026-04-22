<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $categoryService,
    ) {}

    public function index()
    {
        $categories = Category::query()
            ->ordered()
            ->get();

        return view('admin.categories.index', compact('categories'));
    }

    public function create()
    {
        $categories = $this->categoryService->getNestedSelectOptions(activeOnly: false);

        return view('admin.categories.create', compact('categories'));
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $category = Category::create([
            'name'        => $validated['name'],
            'slug'        => $validated['slug'] ?? null,
            'parent_id'   => $validated['parent_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'icon'        => $validated['icon'] ?? null,
            'sort_order'  => $validated['sort_order'] ?? 0,
            'commission_rate' => $validated['commission_rate'] ?? null,
            'is_active'   => $validated['is_active'] ?? true,
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('categories', 'public');
            $category->update(['image_path' => $path]);
        }

        $this->categoryService->invalidateCache();

        return redirect()->route('admin.categories.index')
            ->with('status', "Category \"{$category->name}\" created.");
    }

    public function edit(Category $category)
    {
        $categories = $this->categoryService->getNestedSelectOptions(activeOnly: false);
        // Remove self and descendants from parent options
        $excludeIds = array_merge([$category->id], $category->descendant_ids);
        $categories = array_filter($categories, fn ($v, $k) => ! in_array($k, $excludeIds), ARRAY_FILTER_USE_BOTH);

        return view('admin.categories.edit', compact('category', 'categories'));
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $validated = $request->validated();

        $category->update([
            'name'        => $validated['name'] ?? $category->name,
            'slug'        => $validated['slug'] ?? $category->slug,
            'parent_id'   => array_key_exists('parent_id', $validated) ? $validated['parent_id'] : $category->parent_id,
            'description' => $validated['description'] ?? $category->description,
            'icon'        => $validated['icon'] ?? $category->icon,
            'sort_order'  => $validated['sort_order'] ?? $category->sort_order,
            'commission_rate' => array_key_exists('commission_rate', $validated) ? $validated['commission_rate'] : $category->commission_rate,
            'is_active'   => $validated['is_active'] ?? $category->is_active,
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('categories', 'public');
            $category->update(['image_path' => $path]);
        }

        $this->categoryService->invalidateCache();

        return redirect()->route('admin.categories.index')
            ->with('status', "Category \"{$category->name}\" updated.");
    }

    public function destroy(Category $category): RedirectResponse
    {
        // Reassign children to parent before deleting
        Category::where('parent_id', $category->id)
            ->update(['parent_id' => $category->parent_id]);

        $name = $category->name;
        $category->delete();

        $this->categoryService->invalidateCache();

        return redirect()->route('admin.categories.index')
            ->with('status', "Category \"{$name}\" deleted. Children reassigned.");
    }

    public function toggle(Request $request, Category $category): JsonResponse|RedirectResponse
    {
        $category->update(['is_active' => ! $category->is_active]);
        $this->categoryService->invalidateCache();

        if (! $request->expectsJson()) {
            return back()->with('status', $category->is_active ? 'Category activated.' : 'Category deactivated.');
        }

        return response()->json([
            'is_active' => $category->is_active,
            'message'   => $category->is_active ? 'Category activated.' : 'Category deactivated.',
        ]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer', 'exists:categories,id'],
            'items.*.parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($validated['items'] as $item) {
            Category::where('id', $item['id'])->update([
                'parent_id'  => $item['parent_id'] ?? null,
                'sort_order' => $item['sort_order'],
            ]);
        }

        // Recompute depth/path for moved items
        foreach ($validated['items'] as $item) {
            $category = Category::find($item['id']);
            if ($category) {
                $category->computeDepthAndPath();
                $category->saveQuietly();
            }
        }

        $this->categoryService->invalidateCache();

        return response()->json(['success' => true, 'message' => 'Categories reordered.']);
    }
}
