<?php

namespace App\Notifications;

use App\Models\SupportConversation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupportEscalationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public SupportConversation $conversation,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Support conversation escalated')
            ->line("Support conversation #{$this->conversation->id} was escalated to human support.")
            ->action('Open Support Inbox', route('admin.support.show', $this->conversation));
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload());
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload();
    }

    private function payload(): array
    {
        return [
            'type' => 'support_escalation',
            'conversation_id' => $this->conversation->id,
            'message' => "Support conversation #{$this->conversation->id} requires human follow-up.",
            'title' => 'Support escalation',
            'status' => $this->conversation->status,
        ];
    }
}
