<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RefundProcessedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int    $auctionId,
        public string $auctionTitle,
        public float  $amount,
        public string $reason,
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
            ->subject('Refund Processed — ' . $this->auctionTitle)
            ->greeting("Hi {$notifiable->name}!")
            ->line("A refund of \$" . number_format($this->amount, 2) . " has been processed for:")
            ->line("**{$this->auctionTitle}**")
            ->line("**Reason:** {$this->reason}")
            ->action('View Wallet', url('/dashboard/wallet'))
            ->line('The funds have been credited back to your wallet.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'          => 'refund_processed',
            'auction_id'    => $this->auctionId,
            'auction_title' => $this->auctionTitle,
            'amount'        => $this->amount,
            'reason'        => $this->reason,
            'message'       => "Refund of \$" . number_format($this->amount, 2) . " processed for \"{$this->auctionTitle}\".",
        ];
    }
}
