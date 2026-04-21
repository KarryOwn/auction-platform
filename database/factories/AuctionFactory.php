<?php

namespace Database\Factories;

use App\Models\Auction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Auction>
 */
class AuctionFactory extends Factory
{
    protected $model = Auction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startAt = $this->faker->dateTimeBetween('-2 days', '-10 minutes');
        $endAt = $this->faker->dateTimeBetween('+10 minutes', '+2 days');
        $startingPrice = $this->faker->randomFloat(2, 10, 500);

        return [
            'user_id'                 => User::factory(),
            'title'                   => ucfirst($this->faker->words(3, true)) . '-' . $this->faker->year,
            'description'             => $this->faker->paragraph,
            'starting_price'          => $startingPrice,
            'current_price'           => $startingPrice,
            'min_bid_increment'       => $this->faker->randomElement([0.50, 1.00, 5.00, 10.00]),
            'snipe_threshold_seconds' => 30,
            'snipe_extension_seconds' => 30,
            'max_extensions'          => 10,
            'currency'                => 'USD',
            'start_time'              => $startAt,
            'end_time'                => $endAt,
            'status'                  => Auction::STATUS_ACTIVE,
        ];
    }

    /**
     * Auction with a reserve price.
     */
    public function withReserve(float $reservePrice = null): static
    {
        return $this->state(fn (array $attrs) => [
            'reserve_price' => $reservePrice ?? $attrs['starting_price'] * 2,
            'reserve_met'   => false,
        ]);
    }

    /**
     * Featured auction.
     */
    public function featured(): static
    {
        return $this->state(fn () => [
            'is_featured'    => true,
            'featured_until' => now()->addDays(7),
        ]);
    }

    /**
     * Draft auction.
     */
    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => Auction::STATUS_DRAFT,
        ]);
    }

    /**
     * Completed auction with a winner.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attrs) => [
            'status'             => Auction::STATUS_COMPLETED,
            'winner_id'          => User::factory(),
            'winning_bid_amount' => $attrs['starting_price'] + $this->faker->randomFloat(2, 10, 200),
            'closed_at'          => now(),
            'end_time'           => now()->subMinutes(5),
            'start_time'         => now()->subHours(2),
        ]);
    }

    /**
     * Cancelled auction.
     */
    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status'    => Auction::STATUS_CANCELLED,
            'closed_at' => now(),
        ]);
    }

    /**
     * Auction ending soon (within the given minutes).
     */
    public function endingSoon(int $minutes = 5): static
    {
        return $this->state(fn () => [
            'status'     => Auction::STATUS_ACTIVE,
            'start_time' => now()->subHour(),
            'end_time'   => now()->addMinutes($minutes),
        ]);
    }

    public function withImages(int $count = 3): static
    {
        return $this->afterCreating(function (Auction $auction) use ($count) {
            for ($index = 1; $index <= $count; $index++) {
                $auction->addMediaFromString($this->createSeedImagePng())
                    ->usingFileName("seed-{$auction->id}-{$index}.png")
                    ->toMediaCollection('images');
            }
        });
    }

    private function createSeedImagePng(): string
    {
        $width = 1200;
        $height = 900;

        $image = imagecreatetruecolor($width, $height);
        if ($image === false) {
            throw new \RuntimeException('Unable to initialize seed image canvas.');
        }

        $baseColor = imagecolorallocate(
            $image,
            random_int(50, 160),
            random_int(70, 190),
            random_int(90, 220)
        );
        $accentColor = imagecolorallocate(
            $image,
            random_int(180, 255),
            random_int(130, 230),
            random_int(80, 200)
        );
        $textColor = imagecolorallocate($image, 255, 255, 255);

        imagefilledrectangle($image, 0, 0, $width, $height, $baseColor);
        imagefilledellipse($image, (int) ($width * 0.28), (int) ($height * 0.34), 520, 520, $accentColor);
        imagefilledellipse($image, (int) ($width * 0.78), (int) ($height * 0.72), 420, 420, $accentColor);
        imagestring($image, 5, 30, 30, 'Seeded Auction Image', $textColor);

        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        if (! is_string($png) || $png === '') {
            throw new \RuntimeException('Failed to generate seed image PNG data.');
        }

        return $png;
    }

    public function withVideo(): static
    {
        return $this->state(fn () => [
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);
    }

    /**
     * Auction with a specific condition.
     */
    public function withCondition(string $condition = 'new'): static
    {
        return $this->state(fn () => [
            'condition' => $condition,
        ]);
    }

    /**
     * Auction with a brand.
     */
    public function withBrand(int $brandId): static
    {
        return $this->state(fn () => [
            'brand_id' => $brandId,
        ]);
    }

    /**
     * Auction with a SKU.
     */
    public function withSku(string $sku = null): static
    {
        return $this->state(fn (array $attrs) => [
            'sku' => $sku ?? strtoupper($this->faker->bothify('??-####-???')),
        ]);
    }

    /**
     * Auction with categories (attach after creation).
     */
    public function withCategories(array $categoryIds): static
    {
        return $this->afterCreating(function (Auction $auction) use ($categoryIds) {
            $syncData = [];
            foreach ($categoryIds as $i => $catId) {
                $syncData[$catId] = ['is_primary' => $i === 0];
            }
            $auction->categories()->sync($syncData);
        });
    }

    /**
     * Auction with tags (attach after creation).
     */
    public function withTags(array $tagIds): static
    {
        return $this->afterCreating(function (Auction $auction) use ($tagIds) {
            $auction->tags()->sync($tagIds);
        });
    }
}
