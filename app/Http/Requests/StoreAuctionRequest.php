<?php

namespace App\Http\Requests;

use App\Models\Auction;
use App\Rules\ValidVideoUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAuctionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Auction::class);
    }

    public function rules(): array
    {
        $currencies = config('auction.supported_currencies', [config('auction.currency', 'USD')]);

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            'starting_price' => ['required', 'numeric', 'min:0.01'],
            'reserve_price' => ['nullable', 'numeric', 'gt:starting_price'],
            'min_bid_increment' => ['nullable', 'numeric', 'min:0.01'],
            'start_time' => ['nullable', 'date', 'after:now'],
            'end_time' => ['required', 'date', 'after:start_time'],
            'currency' => ['nullable', 'string', Rule::in($currencies)],
            'snipe_threshold_seconds' => ['nullable', 'integer', 'between:15,300'],
            'snipe_extension_seconds' => ['nullable', 'integer', 'between:15,300'],
            'max_extensions' => ['nullable', 'integer', 'between:1,50'],
            'video_url' => ['nullable', 'url', app(ValidVideoUrl::class)],
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
