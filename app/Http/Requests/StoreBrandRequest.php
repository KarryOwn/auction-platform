<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin' || $this->user()?->role === 'moderator';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('brands', 'name')],
            'logo' => ['nullable', 'image', 'max:1024'],
            'website' => ['nullable', 'url', 'max:255'],
            'is_verified' => ['nullable', 'boolean'],
        ];
    }
}
