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
        // Flush auction-related Redis keys so stale prices don't persist
        $this->command->info('Flushing Redis auction keys...');
        $this->flushAuctionKeys();

        // User::factory(10)->create();
        User::updateOrCreate(
            ['email' => 'hoangkhoa714@gmail.com'],
            [
                'name'           => 'KarryOwn',
                'password'       => bcrypt('khoaprovip01'),
                'role'           => 'user',
                'wallet_balance' => 999999,
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name'           => 'Admin User',
                'password'       => bcrypt('admin'),
                'role'           => 'admin',
                'wallet_balance' => 10000.00,
            ]
        );

        User::updateOrCreate(
            ['email' => 'mod@example.com'],
            [
                'name'           => 'Moderator',
                'password'       => bcrypt('moderator'),
                'role'           => 'moderator',
                'wallet_balance' => 5000.00,
            ]
        );

        $targetUsers = 1003; // 3 fixed accounts + 1000 generated users
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

        $this->command->info('Creating 50 auctions...');
        $leafCategories = Category::whereDoesntHave('children')->pluck('id')->all();
        $brandIds = Brand::pluck('id')->all();
        $tagIds = Tag::pluck('id')->all();
        $conditions = array_keys(Auction::CONDITIONS);

        Auction::factory(50)->create([
            'status'   => 'active',
            'end_time' => now()->addHour(),
        ])->each(function (Auction $auction) use ($leafCategories, $brandIds, $tagIds, $conditions) {
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
     * Remove all auction:* keys from Redis to prevent stale price data
     * after a database refresh.
     */
    private function flushAuctionKeys(): void
    {
        $prefix = config('database.redis.options.prefix', '');
        $pattern = $prefix . 'auction:*';
        $cursor = null;

        do {
            $result = Redis::scan($cursor, ['match' => $pattern, 'count' => 200]);

            // Redis::scan returns false when iteration is complete or no keys found
            if ($result === false) {
                break;
            }

            [$cursor, $keys] = $result;

            if (! empty($keys)) {
                $unprefixed = array_map(fn ($k) => str_replace($prefix, '', $k), $keys);
                Redis::del($unprefixed);
            }
        } while ($cursor);
    }
}
