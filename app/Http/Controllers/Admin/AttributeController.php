<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAttributeRequest;
use App\Models\Attribute;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttributeController extends Controller
{
    public function __construct(
        private readonly CategoryService $categoryService,
    ) {}

    public function index()
    {
        $attributes = Attribute::with('categories')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(25);

        return view('admin.attributes.index', compact('attributes'));
    }

    public function create()
    {
        $categories = $this->categoryService->getNestedSelectOptions(activeOnly: false);

        return view('admin.attributes.create', compact('categories'));
    }

    public function store(StoreAttributeRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $attribute = Attribute::create([
            'name'          => $validated['name'],
            'slug'          => $validated['slug'] ?? null,
            'type'          => $validated['type'],
            'unit'          => $validated['unit'] ?? null,
            'options'       => $validated['type'] === 'select' ? ($validated['options'] ?? []) : null,
            'is_filterable' => $validated['is_filterable'] ?? false,
            'is_required'   => $validated['is_required'] ?? false,
            'sort_order'    => $validated['sort_order'] ?? 0,
        ]);

        if (! empty($validated['category_ids'])) {
            $syncData = [];
            foreach ($validated['category_ids'] as $catId) {
                $syncData[$catId] = ['is_required' => $validated['is_required'] ?? false];
            }
            $attribute->categories()->sync($syncData);
        }

        $this->categoryService->invalidateCache();

        return redirect()->route('admin.attributes.index')
            ->with('status', "Attribute \"{$attribute->name}\" created.");
    }

    public function edit(Attribute $attribute)
    {
        $attribute->load('categories');
        $categories = $this->categoryService->getNestedSelectOptions(activeOnly: false);

        return view('admin.attributes.edit', compact('attribute', 'categories'));
    }

    public function update(Request $request, Attribute $attribute): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:50'],
            'slug' => ['nullable', 'string', 'max:60', Rule::unique('attributes', 'slug')->ignore($attribute->id)],
            'type' => ['sometimes', 'string', Rule::in(['text', 'number', 'select', 'boolean'])],
            'unit' => ['nullable', 'string', 'max:20'],
            'options' => ['nullable', 'array'],
            'options.*' => ['string', 'max:100'],
            'is_filterable' => ['nullable', 'boolean'],
            'is_required' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ]);

        $attribute->update([
            'name'          => $validated['name'] ?? $attribute->name,
            'slug'          => $validated['slug'] ?? $attribute->slug,
            'type'          => $validated['type'] ?? $attribute->type,
            'unit'          => $validated['unit'] ?? $attribute->unit,
            'options'       => ($validated['type'] ?? $attribute->type) === 'select' ? ($validated['options'] ?? $attribute->options) : null,
            'is_filterable' => $validated['is_filterable'] ?? $attribute->is_filterable,
            'is_required'   => $validated['is_required'] ?? $attribute->is_required,
            'sort_order'    => $validated['sort_order'] ?? $attribute->sort_order,
        ]);

        if (array_key_exists('category_ids', $validated)) {
            $syncData = [];
            foreach ($validated['category_ids'] ?? [] as $catId) {
                $syncData[$catId] = ['is_required' => $validated['is_required'] ?? false];
            }
            $attribute->categories()->sync($syncData);
        }

        $this->categoryService->invalidateCache();

        return redirect()->route('admin.attributes.index')
            ->with('status', "Attribute \"{$attribute->name}\" updated.");
    }

    public function destroy(Attribute $attribute): RedirectResponse
    {
        $name = $attribute->name;
        $attribute->values()->delete();
        $attribute->categories()->detach();
        $attribute->delete();

        $this->categoryService->invalidateCache();

        return redirect()->route('admin.attributes.index')
            ->with('status', "Attribute \"{$name}\" deleted.");
    }
}
