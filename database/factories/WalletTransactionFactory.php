<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WalletTransaction>
 */
class WalletTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => 'deposit',
            'amount' => 10.0,
            'balance_after' => 10.0,
            'description' => 'Initial deposit',
        ];
    }
}
