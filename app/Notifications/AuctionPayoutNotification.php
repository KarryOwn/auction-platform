<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AuctionPayoutNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int    $auctionId,
        public string $auctionTitle,
        public float  $totalAmount,
        public float  $sellerAmount,
        public float  $platformFee,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Payout Received — ' . $this->auctionTitle)
            ->greeting("Hi {$notifiable->name}!")
            ->line("Great news! Your auction \"{$this->auctionTitle}\" has been paid.")
            ->line("**Sale Price:** \$" . number_format($this->totalAmount, 2))
            ->line("**Platform Fee:** \$" . number_format($this->platformFee, 2))
            ->line("**Your Payout:** \$" . number_format($this->sellerAmount, 2))
            ->action('View Wallet', url('/dashboard/wallet'))
            ->line('The funds have been credited to your wallet.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'          => 'auction_payout',
            'auction_id'    => $this->auctionId,
            'auction_title' => $this->auctionTitle,
            'total_amount'  => $this->totalAmount,
            'seller_amount' => $this->sellerAmount,
            'platform_fee'  => $this->platformFee,
            'message'       => "Payout of \$" . number_format($this->sellerAmount, 2) . " received for \"{$this->auctionTitle}\".",
        ];
    }
}
