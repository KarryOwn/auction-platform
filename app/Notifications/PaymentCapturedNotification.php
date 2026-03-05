<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentCapturedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int    $auctionId,
        public string $auctionTitle,
        public float  $amount,
        public int    $invoiceId,
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
            ->subject('Payment Captured — ' . $this->auctionTitle)
            ->greeting("Hi {$notifiable->name}!")
            ->line("Your payment of \${$this->formatAmount()} has been automatically captured for:")
            ->line("**{$this->auctionTitle}**")
            ->action('View Invoice', url("/dashboard/invoices/{$this->invoiceId}"))
            ->line('Thank you for your purchase!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'          => 'payment_captured',
            'auction_id'    => $this->auctionId,
            'auction_title' => $this->auctionTitle,
            'amount'        => $this->amount,
            'invoice_id'    => $this->invoiceId,
            'message'       => "Payment of \${$this->formatAmount()} captured for \"{$this->auctionTitle}\".",
        ];
    }

    protected function formatAmount(): string
    {
        return number_format($this->amount, 2);
    }
}
