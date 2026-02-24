<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin' || $this->user()?->role === 'moderator';
    }

    public function rules(): array
    {
        $brand = $this->route('brand');

        return [
            'name' => ['sometimes', 'string', 'max:100', Rule::unique('brands', 'name')->ignore($brand?->id)],
            'logo' => ['nullable', 'image', 'max:1024'],
            'website' => ['nullable', 'url', 'max:255'],
            'is_verified' => ['nullable', 'boolean'],
        ];
    }
}
