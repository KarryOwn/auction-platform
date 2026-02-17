<?php

namespace App\Notifications;

use App\Models\SellerApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SellerApplicationSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SellerApplication $application) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New seller application submitted')
            ->line("{$this->application->user->name} submitted a seller application.")
            ->action('Review Application', url('/admin/seller-applications/'.$this->application->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'seller_application_submitted',
            'application_id' => $this->application->id,
            'user_id' => $this->application->user_id,
            'status' => $this->application->status,
        ];
    }
}
