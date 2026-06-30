<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Auction;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Tag;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Redis;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Flush Redis so stale cache/queue/auction data does not survive fresh seeds
        $this->command->info('Flushing Redis connections...');
        $this->flushRedisConnections();

        // User::factory(10)->create();
        User::updateOrCreate(
            ['email' => 'hoangkhoa714@gmail.com'],
            [
                'name'           => 'KarryOwn',
                'password'       => bcrypt('khoaprovip01'),
                'role'           => 'user',
                'wallet_balance' => 10000,
            ]
        );

        User::updateOrCreate(
            ['email' => 'buyer@buyer.com'],
            [
                'name'           => 'buyer',
                'password'       => bcrypt('buyer'),
                'role'           => 'user',
                'wallet_balance' => 10000,
            ]
        );

        User::updateOrCreate(
            ['email' => 'seller@seller.com'],
            [
                'name'           => 'seller',
                'password'       => bcrypt('seller'),
                'role' => User::ROLE_SELLER,
                'seller_verified_at' => now(),
                'seller_application_status' => 'approved',
                'seller_rejected_reason' => null,
                'seller_slug' => 'seller',
                'wallet_balance' => 10000,
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name'           => 'Admin User',
                'password'       => bcrypt('admin'),
                'role'           => 'admin',
                'wallet_balance' => 10000.00,
            ]
        );

        $targetUsers = 1003; 
        $missingUsers = max(0, $targetUsers - User::count());

        if ($missingUsers > 0) {
            $this->command->info("Creating {$missingUsers} users...");
            User::factory($missingUsers)->create();
        } else {
            $this->command->info('Skipping bulk user creation (target already reached).');
        }

        // Seed categories, brands, tags, attributes
        $this->call([
            CategorySeeder::class,
            BrandSeeder::class,
            TagSeeder::class,
            AttributeSeeder::class,
            PriceModelSeeder::class,
        ]);

        $this->command->info('Creating 50 auctions with pictures...');
        $leafCategories = Category::whereDoesntHave('children')->pluck('id')->all();
        $brandIds = Brand::pluck('id')->all();
        $tagIds = Tag::pluck('id')->all();
        $conditions = array_keys(Auction::CONDITIONS);

        $this->command->info('Generating seed image...');
        $seedImagePng = $this->createSeedImagePng();

        Auction::factory(50)->create([
            'status'   => 'active',
            'end_time' => now()->addHour(),
        ])->each(function (Auction $auction) use ($leafCategories, $brandIds, $tagIds, $conditions, $seedImagePng) {
            $auction->addMediaFromString($seedImagePng)
                ->usingFileName("seed-{$auction->id}-1.png")
                ->toMediaCollection('images');

            // Assign 1-2 random categories
            $catIds = collect($leafCategories)->random(min(rand(1, 2), count($leafCategories)))->all();
            $syncData = [];
            foreach ($catIds as $i => $catId) {
                $syncData[$catId] = ['is_primary' => $i === 0];
            }
            $auction->categories()->sync($syncData);

            // Assign 1-3 random tags
            $randomTags = collect($tagIds)->random(min(rand(1, 3), count($tagIds)))->all();
            $auction->tags()->sync($randomTags);

            // Assign random condition and brand
            $auction->update([
                'condition' => $conditions[array_rand($conditions)],
                'brand_id'  => $brandIds[array_rand($brandIds)],
            ]);
        });
    }

    /**
     * Flush all configured Redis connections for this app after DB refresh.
     */
    private function flushRedisConnections(): void
    {
        $connections = collect(config('database.redis', []))
            ->except(['client', 'options'])
            ->filter(fn ($connectionConfig) => is_array($connectionConfig))
            ->keys();

        foreach ($connections as $connection) {
            Redis::connection($connection)->flushdb();
            $this->command->line(" - Cleared Redis [{$connection}] database");
        }
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
}
