<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class WorkorderDeleted extends Notification
{
    use Queueable;

    public function __construct(public int $workorderId) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Munkalap törölve')
            ->greeting('Tisztelt Ügyfelünk!')
            ->line('A következő munkalapot töröltük: #' . $this->workorderId)
            ->line('Ha kérdése van, vegye fel velünk a kapcsolatot.');
    }
}

