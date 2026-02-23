<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AuctionWonNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int    $auctionId,
        public string $auctionTitle,
        public float  $winningAmount,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable->wantsNotification('auction_won', 'email')) {
            $channels[] = 'mail';
        }

        if ($notifiable->wantsNotification('auction_won', 'push')) {
            $channels[] = 'broadcast';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("🎉 You won: {$this->auctionTitle}")
            ->greeting("Congratulations {$notifiable->name}!")
            ->line("You've won the auction \"{$this->auctionTitle}\" with a winning bid of \${$this->winningAmount}.")
            ->action('View Auction', url("/auctions/{$this->auctionId}"))
            ->line('Please complete payment to finalize your purchase.');
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
            'type'           => 'auction_won',
            'auction_id'     => $this->auctionId,
            'auction_title'  => $this->auctionTitle,
            'winning_amount' => $this->winningAmount,
            'message'        => "🎉 You won \"{$this->auctionTitle}\" for \${$this->winningAmount}!",
        ];
    }
}
