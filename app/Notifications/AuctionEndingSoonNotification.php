<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AuctionEndingSoonNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int    $auctionId,
        public string $auctionTitle,
        public string $endsAt,
        public string $timeRemaining,
        public float  $currentPrice,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable->wantsNotification('auction_ending', 'email')) {
            $channels[] = 'mail';
        }

        if ($notifiable->wantsNotification('auction_ending', 'push')) {
            $channels[] = 'broadcast';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Auction ending soon: {$this->auctionTitle}")
            ->greeting("Hello {$notifiable->name},")
            ->line("The auction \"{$this->auctionTitle}\" is ending in {$this->timeRemaining}.")
            ->line("Current price: \${$this->currentPrice}")
            ->action('View Auction', url("/auctions/{$this->auctionId}"))
            ->line("Don't miss your chance — place a bid before it ends!");
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload());
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload();
    }

    protected function payload(): array
    {
        return [
            'type'           => 'auction_ending',
            'auction_id'     => $this->auctionId,
            'auction_title'  => $this->auctionTitle,
            'ends_at'        => $this->endsAt,
            'time_remaining' => $this->timeRemaining,
            'current_price'  => $this->currentPrice,
            'message'        => "\"{$this->auctionTitle}\" is ending in {$this->timeRemaining}!",
        ];
    }
}
