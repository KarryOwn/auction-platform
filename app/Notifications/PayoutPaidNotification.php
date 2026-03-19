<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayoutPaidNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public float $amount,
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
            ->subject('Withdrawal Complete')
            ->greeting("Hi {$notifiable->name}!")
            ->line("Your withdrawal of \$" . number_format($this->amount, 2) . " has been deposited to your bank account.")
            ->line('The funds have been deducted from your wallet balance.')
            ->action('View Wallet', url('/dashboard/wallet'))
            ->line('Thank you for using our platform!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'    => 'payout_paid',
            'amount'  => $this->amount,
            'message' => "Withdrawal of \$" . number_format($this->amount, 2) . " has been deposited to your bank account.",
        ];
    }
}
