<?php

namespace App\Http\Requests;

use App\Models\Auction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PlaceBidRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        // Must be authenticated and not banned
        return $user && ! $user->isBanned() && ! $user->isStaff();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999999.99'],
        ];
    }

    /**
     * Add auction-specific validation after the basic rules pass.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var Auction $auction */
                $auction = $this->route('auction');
                $user    = $this->user();
                $amount  = round((float) $this->validated()['amount'], 2);

                if (! $auction->isActive()) {
                    $validator->errors()->add('auction', 'This auction is no longer active.');
                    return;
                }

                if ($auction->user_id === $user->id) {
                    $validator->errors()->add('auction', 'You cannot bid on your own auction.');
                    return;
                }

                $minimumBid = $auction->minimumNextBid();
                $amountCents = (int) round($amount * 100);
                $minimumBidCents = (int) round($minimumBid * 100);

                if ($amountCents < $minimumBidCents) {
                    $validator->errors()->add(
                        'amount',
                        'Your bid must be at least $'.number_format($minimumBid, 2).'. The current price is $'.number_format((float) $auction->current_price, 2).'.'
                    );
                }
            },
        ];
    }

    /**
     * Custom error messages.
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'A bid amount is required.',
            'amount.numeric'  => 'The bid amount must be a number.',
            'amount.min'      => 'The bid amount must be at least $0.01.',
            'amount.max'      => 'The bid amount exceeds the maximum allowed.',
        ];
    }
}
