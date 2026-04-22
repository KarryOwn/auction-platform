<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RedisDownNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('CRITICAL: Redis is unavailable — Bidding System Degraded')
            ->greeting('Redis Unavailable')
            ->line('The platform has automatically fallen back to the PessimisticSqlEngine because Redis was unreachable.')
            ->line('Timestamp: ' . now()->toDateTimeString())
            ->line('Please investigate the Redis infrastructure immediately.')
            ->line('Once Redis recovers, you must reconcile prices using:')
            ->line('sail artisan auction:sync-prices')
            ->line('Then restart Horizon workers.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
