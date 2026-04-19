<?php

namespace App\Notifications;

use App\Models\Dispute;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
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
        return ['database'];
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
