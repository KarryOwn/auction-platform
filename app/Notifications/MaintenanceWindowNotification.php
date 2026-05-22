<?php

namespace App\Notifications;

use App\Models\MaintenanceWindow;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class MaintenanceWindowNotification extends Notification
{
    use Queueable;

    public function __construct(
        public MaintenanceWindow $window,
        public string $event = 'scheduled',
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
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
        $label = match ($this->event) {
            'updated' => 'Maintenance window updated',
            'cancelled' => 'Maintenance window cancelled',
            'active' => 'Maintenance has started',
            default => 'Maintenance window scheduled',
        };

        $message = match ($this->event) {
            'updated' => 'The scheduled maintenance window has been updated.',
            'cancelled' => 'The scheduled maintenance window has been cancelled.',
            'active' => 'The platform is now in scheduled maintenance mode.',
            default => 'Scheduled maintenance is coming up. Please save your work before it starts.',
        };

        return [
            'type' => 'maintenance_window',
            'event' => $this->event,
            'maintenance_window_id' => $this->window->id,
            'scheduled_start' => $this->window->scheduled_start?->toIso8601String(),
            'scheduled_end' => $this->window->scheduled_end?->toIso8601String(),
            'title' => $label,
            'message' => $message,
            'body' => $this->window->message,
            'url' => route('dashboard'),
        ];
    }
}
