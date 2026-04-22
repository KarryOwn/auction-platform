<?php

namespace App\Notifications;

use App\Models\Auction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AuthCertStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Auction $auction)
    {
        $this->queue = 'notifications';
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $verified = $this->auction->authenticity_cert_status === 'verified';
        $auctionUrl = in_array($this->auction->status, [Auction::STATUS_ACTIVE, Auction::STATUS_COMPLETED], true)
            ? route('auctions.show', $this->auction)
            : route('seller.auctions.edit', $this->auction);

        $mail = (new MailMessage)
            ->subject($verified ? 'Authenticity certificate verified' : 'Authenticity certificate review update')
            ->greeting("Hello {$notifiable->name},")
            ->line("Your certificate for \"{$this->auction->title}\" was ".($verified ? 'verified' : 'reviewed and rejected').'.');

        if ($this->auction->authenticity_cert_notes) {
            $mail->line('Notes: '.$this->auction->authenticity_cert_notes);
        }

        return $mail
            ->action('Review Listing', $auctionUrl)
            ->line($verified
                ? 'The listing now shows an authenticity verified badge.'
                : 'You can upload a new certificate from the seller edit page.');
    }

    public function toArray(object $notifiable): array
    {
        $verified = $this->auction->authenticity_cert_status === 'verified';

        return [
            'type' => 'authenticity_certificate_status',
            'auction_id' => $this->auction->id,
            'title' => $this->auction->title,
            'status' => $this->auction->authenticity_cert_status,
            'notes' => $this->auction->authenticity_cert_notes,
            'message' => $verified
                ? "Your authenticity certificate for {$this->auction->title} was verified."
                : "Your authenticity certificate for {$this->auction->title} was rejected.",
        ];
    }
}
