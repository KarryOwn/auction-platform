<?php

namespace App\Http\Requests;

use App\Models\Auction;
use App\Rules\ValidVideoUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAuctionRequest extends FormRequest
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
        /** @var Auction $auction */
        $auction = $this->route('auction');

        return (bool) $this->user()?->can('update', $auction);
    }

    public function rules(): array
    {
        $currencies = config('auction.supported_currencies', [config('auction.currency', 'USD')]);
        $maxCategories = config('auction.categories.max_per_auction', 3);
        $maxTags = config('auction.tags.max_per_auction', 10);
        $conditions = array_keys(config('auction.conditions', \App\Models\Auction::CONDITIONS));

        return [
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'min:20', 'max:5000'],
            'starting_price' => ['sometimes', 'nullable', 'numeric', 'min:0.01'],
            'reserve_price' => ['sometimes', 'nullable', 'numeric'],
            'reserve_price_visible' => ['sometimes', 'nullable', 'boolean'],
            'buy_it_now_price' => ['sometimes', 'nullable', 'numeric', 'gt:starting_price'],
            'buy_it_now_enabled' => ['sometimes', 'nullable', 'boolean'],
            'buy_it_now_expires_at' => ['sometimes', 'nullable', 'date', 'after:now'],
            'min_bid_increment' => ['sometimes', 'nullable', 'numeric', 'min:0.01'],
            'start_time' => ['sometimes', 'nullable', 'date'],
            'end_time' => ['sometimes', 'nullable', 'date'],
            'currency' => ['sometimes', 'nullable', 'string', Rule::in($currencies)],
            'snipe_threshold_seconds' => ['sometimes', 'nullable', 'integer', 'between:15,300'],
            'snipe_extension_seconds' => ['sometimes', 'nullable', 'integer', 'between:15,300'],
            'max_extensions' => ['sometimes', 'nullable', 'integer', 'between:1,50'],
            'video_url' => ['nullable', 'url', app(ValidVideoUrl::class)],

            // Product metadata
            'categories' => ['sometimes', 'array', 'min:1', "max:{$maxCategories}"],
            'categories.*' => ['integer', 'exists:categories,id'],
            'primary_category_id' => ['sometimes', 'integer'],
            'tags' => ['nullable', 'array', "max:{$maxTags}"],
            'tags.*' => ['string', 'max:50', 'min:2'],
            'condition' => ['sometimes', 'nullable', 'string', Rule::in($conditions)],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'sku' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'attributes' => ['nullable', 'array'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                /** @var Auction $auction */
                $auction = $this->route('auction');

                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->filled('reserve_price') && $this->filled('starting_price')) {
                    if ((float) $this->input('reserve_price') <= (float) $this->input('starting_price')) {
                        $validator->errors()->add('reserve_price', 'Reserve price must be greater than starting price.');
                    }
                }

                if ($auction->isActive() && (int) $auction->bid_count > 0) {
                    if ($this->has('starting_price') && (float) $this->input('starting_price') !== (float) $auction->starting_price) {
                        $validator->errors()->add('starting_price', 'Starting price cannot be changed after bids are placed.');
                    }

                    if ($this->has('min_bid_increment') && (float) $this->input('min_bid_increment') !== (float) $auction->min_bid_increment) {
                        $validator->errors()->add('min_bid_increment', 'Minimum bid increment cannot be changed after bids are placed.');
                    }

                    if ($this->filled('end_time') && $auction->end_time !== null) {
                        $proposed = Carbon::parse((string) $this->input('end_time'));
                        if ($proposed->lt($auction->end_time)) {
                            $validator->errors()->add('end_time', 'End time cannot be reduced once bids exist.');
                        }
                    }
                }
            },
        ];
    }
}
