<?php

namespace App\Notifications;

use App\Models\Dispute;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DisputeStatusUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Dispute $dispute)
    {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Dispute status updated')
            ->greeting("Hello {$notifiable->name},")
            ->line('Dispute #'.$this->dispute->id.' is now '.$this->dispute->status_label.'.')
            ->when($this->dispute->resolution_notes, function (MailMessage $mail): MailMessage {
                return $mail->line('Resolution notes: '.$this->dispute->resolution_notes);
            })
            ->action('View Auction', route('auctions.show', $this->dispute->auction));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'dispute_status_updated',
            'dispute_id' => $this->dispute->id,
            'auction_id' => $this->dispute->auction_id,
            'status' => $this->dispute->status,
            'status_label' => $this->dispute->status_label,
            'message' => 'Dispute #'.$this->dispute->id.' is now '.$this->dispute->status_label.'.',
        ];
    }
}
