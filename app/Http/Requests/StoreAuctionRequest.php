<?php

namespace App\Http\Requests;

use App\Models\Auction;
use App\Rules\ValidVideoUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAuctionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('tags'))) {
            $tags = collect(explode(',', $this->input('tags')))
                ->map(fn (string $tag) => trim($tag))
                ->filter()
                ->values()
                ->all();

            $this->merge(['tags' => $tags]);
        }
    }

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Auction::class);
    }

    public function rules(): array
    {
        $currencies = config('auction.supported_currencies', [config('auction.currency', 'USD')]);
        $maxCategories = config('auction.categories.max_per_auction', 3);
        $maxTags = config('auction.tags.max_per_auction', 10);
        $conditions = array_keys(config('auction.conditions', \App\Models\Auction::CONDITIONS));

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            'starting_price' => ['required', 'numeric', 'min:0.01'],
            'reserve_price' => ['nullable', 'numeric', 'gt:starting_price'],
            'reserve_price_visible' => ['nullable', 'boolean'],
            'buy_it_now_price' => ['nullable', 'numeric', 'gt:starting_price'],
            'buy_it_now_enabled' => ['nullable', 'boolean'],
            'buy_it_now_expires_at' => ['nullable', 'date', 'after:now'],
            'min_bid_increment' => ['nullable', 'numeric', 'min:0.01'],
            'start_time' => ['nullable', 'date', 'after_or_equal:now'],
            'end_time' => ['required', 'date', 'after:start_time'],
            'currency' => ['nullable', 'string', Rule::in($currencies)],
            'snipe_threshold_seconds' => ['nullable', 'integer', 'between:15,300'],
            'snipe_extension_seconds' => ['nullable', 'integer', 'between:15,300'],
            'max_extensions' => ['nullable', 'integer', 'between:1,50'],
            'video_url' => ['nullable', 'url', app(ValidVideoUrl::class)],

            // Product metadata
            'categories' => ['required', 'array', 'min:1', "max:{$maxCategories}"],
            'categories.*' => ['integer', 'exists:categories,id'],
            'primary_category_id' => ['nullable', 'integer', 'in_array:categories.*'],
            'tags' => ['nullable', 'array', "max:{$maxTags}"],
            'tags.*' => ['string', 'max:50', 'min:2'],
            'condition' => ['required', 'string', Rule::in($conditions)],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'sku' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'attributes' => ['nullable', 'array'],
            'return_policy_override' => ['nullable', Rule::in(['no_returns', 'returns_accepted', 'custom'])],
            'return_policy_custom_override' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $endTime = $this->date('end_time');
                $startTime = $this->date('start_time');

                if ($endTime === null) {
                    return;
                }

                if ($startTime !== null && $endTime->lessThanOrEqualTo($startTime)) {
                    $validator->errors()->add('end_time', 'End time must be after start time.');
                }

                if ($startTime === null && $endTime->lessThanOrEqualTo(now())) {
                    $validator->errors()->add('end_time', 'End time must be in the future.');
                }
            },
        ];
    }
}
