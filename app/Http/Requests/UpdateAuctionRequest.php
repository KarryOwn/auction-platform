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
    public function authorize(): bool
    {
        /** @var Auction $auction */
        $auction = $this->route('auction');

        return (bool) $this->user()?->can('update', $auction);
    }

    public function rules(): array
    {
        $currencies = config('auction.supported_currencies', [config('auction.currency', 'USD')]);

        return [
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'min:20', 'max:5000'],
            'starting_price' => ['sometimes', 'nullable', 'numeric', 'min:0.01'],
            'reserve_price' => ['sometimes', 'nullable', 'numeric'],
            'min_bid_increment' => ['sometimes', 'nullable', 'numeric', 'min:0.01'],
            'start_time' => ['sometimes', 'nullable', 'date'],
            'end_time' => ['sometimes', 'nullable', 'date'],
            'currency' => ['sometimes', 'nullable', 'string', Rule::in($currencies)],
            'snipe_threshold_seconds' => ['sometimes', 'nullable', 'integer', 'between:15,300'],
            'snipe_extension_seconds' => ['sometimes', 'nullable', 'integer', 'between:15,300'],
            'max_extensions' => ['sometimes', 'nullable', 'integer', 'between:1,50'],
            'video_url' => ['nullable', 'url', app(ValidVideoUrl::class)],
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
