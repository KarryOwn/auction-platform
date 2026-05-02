<?php

namespace App\Console\Commands;

use App\Models\EscrowHold;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SeedStressBots extends Command
{
    protected $signature = 'stress:seed-bots {count=100} {--balance=1000000}';

    protected $description = 'Create or top up isolated stress-test bidder accounts';

    public function handle(): int
    {
        $count = max(1, (int) $this->argument('count'));
        $balance = max(0, (float) $this->option('balance'));

        $this->info("Preparing {$count} stress-test bot accounts with $" . number_format($balance, 2) . ' each...');

        for ($i = 1; $i <= $count; $i++) {
            $number = str_pad((string) $i, 4, '0', STR_PAD_LEFT);

            User::updateOrCreate(
                ['email' => "stress-bot-{$number}@example.test"],
                [
                    'name' => "Stress Bot {$number}",
                    'password' => Hash::make(Str::random(32)),
                    'email_verified_at' => now(),
                    'role' => User::ROLE_USER,
                    'is_banned' => false,
                    'wallet_balance' => $balance,
                    'held_balance' => 0,
                ],
            );
        }

        $botIds = User::where('email', 'like', 'stress-bot-%@example.test')->pluck('id');

        EscrowHold::whereIn('user_id', $botIds)
            ->where('status', EscrowHold::STATUS_ACTIVE)
            ->update([
                'status' => EscrowHold::STATUS_RELEASED,
                'released_at' => now(),
            ]);

        User::whereIn('id', $botIds)->update([
            'wallet_balance' => $balance,
            'held_balance' => 0,
        ]);

        $this->info('Stress bots are ready.');

        return self::SUCCESS;
    }
}
