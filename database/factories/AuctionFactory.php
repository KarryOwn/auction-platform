<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class AuctionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {   
        $startAt = $this->faker->dateTimeBetween('-1 day', '+1 day');
        $endAt = (clone $startAt)->modify('+1 hour');

        return [
            'user_id' => \App\Models\User::factory(),
            'title' => ucfirst($this->faker->words(3, true)) . '-' . $this->faker->year,
            'description' => $this->faker->paragraph,
            'starting_price' => $this->faker->randomFloat(2, 10, 500),
            'current_price' => function (array $attributes) {
                return $attributes['starting_price'];
            },
            'start_time' => $startAt,
            'end_time' => $endAt,
            'status' => 'active',
        ];
    }
}
