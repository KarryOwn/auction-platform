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

        $targetUsers = 1002; // 2 fixed accounts + 1000 generated users
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

        Auction::factory(50)->withImages(1)->create([
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
}
