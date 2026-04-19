<?php

namespace App\Jobs;

use App\Models\Auction;
use App\Models\KeywordAlert;
use App\Notifications\KeywordAlertNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class ProcessKeywordAlerts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $queue = 'notifications';
    public int $tries = 3;
    public array $backoff = [10, 60];

    public function __construct(public readonly int $auctionId) {}

    public function handle(): void
    {
        $auction = Auction::find($this->auctionId);

        if (! $auction || ! $auction->isActive()) {
            return;
        }

        $auctionTitle = Str::lower($auction->title);

        KeywordAlert::active()
            ->with('user')
            ->chunk(100, function ($alerts) use ($auction, $auctionTitle): void {
                foreach ($alerts as $alert) {
                    $user = $alert->user;

                    if (! $user) {
                        continue;
                    }

                    $keyword = trim((string) $alert->keyword);
                    if ($keyword === '') {
                        continue;
                    }

                    if (! Str::contains($auctionTitle, Str::lower($keyword))) {
                        continue;
                    }

                    if ($alert->user_id === $auction->user_id) {
                        continue;
                    }

                    if ($alert->notify_database) {
                        Notification::sendNow($user, new KeywordAlertNotification($auction, $keyword), ['database']);
                    }

                    if ($alert->notify_email) {
                        Mail::queue('emails.keyword-alert', [
                            'auction' => $auction,
                            'keyword' => $keyword,
                            'user' => $user,
                        ], function ($message) use ($user, $keyword): void {
                            $message->to($user->email, $user->name)
                                ->subject("New auction matching '{$keyword}'");
                        });
                    }

                    $alert->forceFill([
                        'last_notified_at' => now(),
                    ])->save();
                }
            });
    }
}