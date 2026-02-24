<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin' || $this->user()?->role === 'moderator';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'slug' => ['nullable', 'string', 'max:60', Rule::unique('attributes', 'slug')],
            'type' => ['required', 'string', Rule::in(['text', 'number', 'select', 'boolean'])],
            'unit' => ['nullable', 'string', 'max:20'],
            'options' => ['nullable', 'array'],
            'options.*' => ['string', 'max:100'],
            'is_filterable' => ['nullable', 'boolean'],
            'is_required' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ];
    }
}
