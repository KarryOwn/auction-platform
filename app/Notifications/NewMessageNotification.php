<?php

namespace App\Notifications;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Conversation $conversation, public Message $message) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New message about an auction')
            ->line('You received a new message on auction: '.$this->conversation->auction->title)
            ->line(str($this->message->body)->limit(120))
            ->action('Open Conversation', url('/messages/'.$this->conversation->id));
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_message',
            'conversation_id' => $this->conversation->id,
            'auction_id' => $this->conversation->auction_id,
            'sender_id' => $this->message->sender_id,
            'preview' => str($this->message->body)->limit(120),
            'created_at' => $this->message->created_at?->toIso8601String(),
        ];
    }
}
