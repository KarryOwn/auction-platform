<?php

namespace App\Notifications;

use App\Models\Auction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KeywordAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Auction $auction,
        public readonly string $keyword,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (method_exists($notifiable, 'wantsNotification')
            && $notifiable->wantsNotification('keyword_alert', 'email')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New auction matching '{$this->keyword}'")
            ->greeting("Hello {$notifiable->name},")
            ->line("A new auction matches your keyword '{$this->keyword}'.")
            ->line("Auction: {$this->auction->title}")
            ->action('View Auction', route('auctions.show', $this->auction->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'auction_id' => $this->auction->id,
            'auction_title' => $this->auction->title,
            'keyword' => $this->keyword,
            'message' => "New auction matching '{$this->keyword}': {$this->auction->title}",
        ];
    }
}