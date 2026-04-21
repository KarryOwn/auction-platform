<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PriceAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $auctionId,
        public readonly string $auctionTitle,
        public readonly float $currentPrice,
        public readonly float $thresholdAmount,
    ) {
        $this->queue = 'notifications';
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Price Alert: {$this->auctionTitle} reached $" . number_format($this->thresholdAmount, 2))
            ->line("An auction you are watching has reached your target price alert of $" . number_format($this->thresholdAmount, 2) . ".")
            ->line("The current bid is now $" . number_format($this->currentPrice, 2) . ".")
            ->action('View Auction', route('auctions.show', $this->auctionId));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'            => 'price_alert',
            'auction_id'      => $this->auctionId,
            'title'           => $this->auctionTitle,
            'current_price'   => $this->currentPrice,
            'threshold_amount'=> $this->thresholdAmount,
            'message'         => "Price alert: {$this->auctionTitle} reached $" . number_format($this->thresholdAmount, 2),
        ];
    }
}
