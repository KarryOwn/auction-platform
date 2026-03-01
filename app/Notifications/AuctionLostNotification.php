<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AuctionLostNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int    $auctionId,
        public string $auctionTitle,
        public float  $finalPrice,
        public float  $yourHighestBid,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable->wantsNotification('auction_lost', 'email')) {
            $channels[] = 'mail';
        }

        if ($notifiable->wantsNotification('auction_lost', 'push')) {
            $channels[] = 'broadcast';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Auction ended: {$this->auctionTitle}")
            ->greeting("Hello {$notifiable->name},")
            ->line("The auction \"{$this->auctionTitle}\" has ended.")
            ->line("Final price: \${$this->finalPrice} — Your highest bid: \${$this->yourHighestBid}")
            ->action('Browse More Auctions', url('/auctions'))
            ->line('Better luck next time!');
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
            'type'             => 'auction_lost',
            'auction_id'       => $this->auctionId,
            'auction_title'    => $this->auctionTitle,
            'final_price'      => $this->finalPrice,
            'your_highest_bid' => $this->yourHighestBid,
            'message'          => "Auction \"{$this->auctionTitle}\" ended at \${$this->finalPrice}.",
        ];
    }
}
