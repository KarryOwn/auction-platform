<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Auction;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

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
}
