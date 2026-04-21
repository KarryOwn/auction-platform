<?php

namespace App\Notifications;

use App\Models\Auction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewSellerListingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Auction $auction)
    {
        $this->queue = 'notifications';
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $sellerName = $this->auction->seller->name;
        
        return (new MailMessage)
            ->subject("New Listing from {$sellerName}: {$this->auction->title}")
            ->line("{$sellerName}, a seller you follow, just published a new auction!")
            ->line("Title: {$this->auction->title}")
            ->line("Starting Price: $" . number_format($this->auction->starting_price, 2))
            ->action('View Auction', route('auctions.show', $this->auction))
            ->line('Happy bidding!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'new_seller_listing',
            'auction_id' => $this->auction->id,
            'title'      => $this->auction->title,
            'seller_name'=> $this->auction->seller->name,
            'message'    => "{$this->auction->seller->name} published a new auction: {$this->auction->title}",
        ];
    }
}
