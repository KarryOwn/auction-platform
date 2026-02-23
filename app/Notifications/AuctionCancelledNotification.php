<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AuctionCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int    $auctionId,
        public string $auctionTitle,
        public string $reason,
        public bool   $isBidder = false,
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
        $message = (new MailMessage)
            ->subject("Auction cancelled: {$this->auctionTitle}")
            ->greeting("Hello {$notifiable->name},");

        if ($this->isBidder) {
            $message->line("An auction you bid on — \"{$this->auctionTitle}\" — has been cancelled.");
        } else {
            $message->line("A watched auction — \"{$this->auctionTitle}\" — has been cancelled.");
        }

        if ($this->reason) {
            $message->line("Reason: {$this->reason}");
        }

        $message->action('Browse Auctions', url('/auctions'))
                ->line('Any held funds for this auction will be released.');

        return $message;
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
            'type'          => 'auction_cancelled',
            'auction_id'    => $this->auctionId,
            'auction_title' => $this->auctionTitle,
            'reason'        => $this->reason,
            'is_bidder'     => $this->isBidder,
            'message'       => "\"{$this->auctionTitle}\" has been cancelled.",
        ];
    }
}
