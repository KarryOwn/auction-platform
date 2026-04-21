<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStorefrontRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->isVerifiedSeller();
    }

    public function rules(): array
    {
        return [
            'seller_bio' => ['nullable', 'string', 'max:2000'],
            'seller_slug' => [
                'required',
                'string',
                'max:100',
                'alpha_dash',
                Rule::unique('users', 'seller_slug')->ignore($this->user()->id),
            ],
            'seller_avatar' => ['nullable', 'image', 'max:2048'],
            'return_policy_type'   => ['required', Rule::in(['no_returns', 'returns_accepted', 'custom'])],
            'return_window_days'   => ['nullable', 'required_if:return_policy_type,returns_accepted', 'integer', 'min:1', 'max:90'],
            'return_policy_custom' => ['nullable', 'required_if:return_policy_type,custom', 'string', 'max:2000'],
        ];
    }
}
