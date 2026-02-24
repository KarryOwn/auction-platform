<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin' || $this->user()?->role === 'moderator';
    }

    public function rules(): array
    {
        $category = $this->route('category');
        $maxDepth = config('auction.categories.max_depth', 3);

        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'parent_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
                function ($attribute, $value, $fail) use ($category, $maxDepth) {
                    if ($value && $category) {
                        // Prevent self-reference
                        if ((int) $value === $category->id) {
                            $fail('A category cannot be its own parent.');
                        }
                        // Prevent circular reference (child becoming own ancestor)
                        $descendantIds = $category->descendant_ids;
                        if (in_array((int) $value, $descendantIds, true)) {
                            $fail('Cannot set a descendant as the parent (circular reference).');
                        }
                        // Depth check
                        $parent = \App\Models\Category::find($value);
                        if ($parent && $parent->depth >= $maxDepth - 1) {
                            $fail("Cannot nest deeper than {$maxDepth} levels.");
                        }
                    }
                },
            ],
            'slug' => ['nullable', 'string', 'max:120', Rule::unique('categories', 'slug')->ignore($category?->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', 'max:100'],
            'image' => ['nullable', 'image', 'max:2048'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
