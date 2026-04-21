<?php

namespace Database\Factories;

use App\Models\Auction;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = (float) fake()->randomFloat(2, 20, 2000);
        $platformFee = round($subtotal * 0.05, 2);
        $sellerAmount = round($subtotal - $platformFee, 2);

        return [
            'invoice_number' => 'INV-' . now()->format('Ymd') . '-' . fake()->unique()->numerify('#####'),
            'auction_id' => Auction::factory(),
            'buyer_id' => User::factory(),
            'seller_id' => User::factory(),
            'subtotal' => $subtotal,
            'platform_fee' => $platformFee,
            'seller_amount' => $sellerAmount,
            'total' => $subtotal,
            'currency' => 'USD',
            'status' => Invoice::STATUS_PAID,
            'issued_at' => now(),
            'paid_at' => now(),
        ];
    }
}
