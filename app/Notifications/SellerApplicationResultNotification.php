<?php

namespace App\Notifications;

use App\Models\SellerApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SellerApplicationResultNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SellerApplication $application) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $approved = $this->application->status === SellerApplication::STATUS_APPROVED;

        $mail = (new MailMessage)
            ->subject($approved ? 'Your seller application was approved' : 'Your seller application was rejected')
            ->greeting("Hello {$notifiable->name},");

        if ($approved) {
            $mail->line('Congratulations, your seller application has been approved.')
                ->action('Open Seller Dashboard', url('/seller/dashboard'));
        } else {
            $mail->line('Your seller application was rejected.')
                ->line('Reason: '.($this->application->rejection_reason ?: 'No reason provided.'))
                ->action('View Status', url('/seller/application-status'));
        }

        return $mail;
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
        $approved = $this->application->status === SellerApplication::STATUS_APPROVED;

        return [
            'type' => 'seller_application_result',
            'application_id' => $this->application->id,
            'status' => $this->application->status,
            'rejection_reason' => $this->application->rejection_reason,
            'title' => $approved ? 'Seller application approved' : 'Seller application rejected',
            'message' => $approved
                ? 'Your seller application has been approved.'
                : 'Your seller application was rejected.'.($this->application->rejection_reason ? ' Reason: '.$this->application->rejection_reason : ''),
        ];
    }
}
