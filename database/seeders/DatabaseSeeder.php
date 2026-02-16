<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Auction;
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
        User::factory()->create([
            'name'           => 'KarryOwn',
            'email'          => 'hoangkhoa714@gmail.com',
            'password'       => bcrypt('khoaprovip01'),
            'role'           => 'user',
            'wallet_balance' => 999999,
        ]);

        User::factory()->create([
            'name'           => 'Admin User',
            'email'          => 'admin@example.com',
            'password'       => bcrypt('admin'),
            'role'           => 'admin',
            'wallet_balance' => 10000.00,
        ]);

        // Moderator user
        User::factory()->create([
            'name'           => 'Moderator',
            'email'          => 'mod@example.com',
            'password'       => bcrypt('moderator'),
            'role'           => 'moderator',
            'wallet_balance' => 5000.00,
        ]);

        $this->command->info('Creating 1,000 users...');
        User::factory(1000)->create();

        $this->command->info('Creating 50 auctions...');
        Auction::factory(50)->create([
            'status'   => 'active',
            'end_time' => now()->addHour(),
        ]);
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
