<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class OfferUpdated extends Notification
{
    use Queueable;

    public function __construct(public int $workorderId, public ?string $status = null) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->status ? 'Árajánlat státusz frissítés' : 'Árajánlat frissült';
        $msg = (new MailMessage)
            ->subject($subject)
            ->greeting('Tisztelt Ügyfelünk!')
            ->line('Az árajánlat frissült a következő munkalaphoz: #' . $this->workorderId);
        if ($this->status) {
            $msg->line('Új státusz: ' . $this->status);
        }
        return $msg;
    }
}

