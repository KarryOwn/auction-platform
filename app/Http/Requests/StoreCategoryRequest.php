<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin' || $this->user()?->role === 'moderator';
    }

    public function rules(): array
    {
        $maxDepth = config('auction.categories.max_depth', 3);

        return [
            'name' => ['required', 'string', 'max:100'],
            'parent_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
                function ($attribute, $value, $fail) use ($maxDepth) {
                    if ($value) {
                        $parent = \App\Models\Category::find($value);
                        if ($parent && $parent->depth >= $maxDepth - 1) {
                            $fail("Cannot nest deeper than {$maxDepth} levels.");
                        }
                    }
                },
            ],
            'slug' => ['nullable', 'string', 'max:120', Rule::unique('categories', 'slug')],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', 'max:100'],
            'image' => ['nullable', 'image', 'max:2048'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
