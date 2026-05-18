<?php

namespace App\Notifications;

use App\Models\SellerApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SellerApplicationSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SellerApplication $application)
    {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $applicant = $this->application->user;

        return (new MailMessage)
            ->subject('Seller application submitted')
            ->greeting("Hello {$notifiable->name},")
            ->line(($applicant?->name ?? 'A user').' submitted a seller application for review.')
            ->line('Review the application details before approving or rejecting seller access.')
            ->action('Review Application', route('admin.seller-applications.show', $this->application));
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
            'type' => 'seller_application_submitted',
            'application_id' => $this->application->id,
            'user_id' => $this->application->user_id,
            'status' => $this->application->status,
            'title' => 'Seller application submitted',
            'message' => 'Seller application has been submitted and needs review.',
            'url' => route('admin.seller-applications.show', $this->application),
        ];
    }
}
