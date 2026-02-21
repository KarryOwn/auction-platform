<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OutbidNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int    $auctionId,
        public string $auctionTitle,
        public float  $outbidAmount,
        public float  $yourAmount = 0,
        public bool   $isWatcher = false,
    ) {}

    /**
     * Deliver via channels based on user preferences.
     */
    public function via(object $notifiable): array
    {
        $channels = ['database']; // always store in database

        if ($notifiable->wantsNotification('outbid', 'email')) {
            $channels[] = 'mail';
        }

        if ($notifiable->wantsNotification('outbid', 'push')) {
            $channels[] = 'broadcast';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->isWatcher
            ? "New bid on watched auction: {$this->auctionTitle}"
            : "You've been outbid on: {$this->auctionTitle}";

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting("Hello {$notifiable->name},");

        if ($this->isWatcher) {
            $message->line("A new bid of \${$this->outbidAmount} was placed on \"{$this->auctionTitle}\".");
        } else {
            $message->line("Someone placed a bid of \${$this->outbidAmount} on \"{$this->auctionTitle}\", surpassing your bid of \${$this->yourAmount}.");
        }

        $message->action('View Auction', url("/auctions/{$this->auctionId}"))
                ->line('Place a higher bid to stay in the lead!');

        return $message;
    }

    /**
     * Broadcast on the user's private channel for real-time outbid alerts.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'type'          => $this->isWatcher ? 'auction_activity' : 'outbid',
            'auction_id'    => $this->auctionId,
            'auction_title' => $this->auctionTitle,
            'outbid_amount' => $this->outbidAmount,
            'your_amount'   => $this->yourAmount,
            'is_watcher'    => $this->isWatcher,
            'message'       => $this->isWatcher
                ? "New bid of \${$this->outbidAmount} on \"{$this->auctionTitle}\""
                : "You've been outbid on \"{$this->auctionTitle}\" — new bid: \${$this->outbidAmount}",
        ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'           => $this->isWatcher ? 'auction_activity' : 'outbid',
            'auction_id'     => $this->auctionId,
            'auction_title'  => $this->auctionTitle,
            'outbid_amount'  => $this->outbidAmount,
            'your_amount'    => $this->yourAmount,
            'is_watcher'     => $this->isWatcher,
            'message'        => $this->isWatcher
                ? "New bid of \${$this->outbidAmount} on \"{$this->auctionTitle}\""
                : "You've been outbid on \"{$this->auctionTitle}\" — new bid: \${$this->outbidAmount}",
        ];
    }
}
