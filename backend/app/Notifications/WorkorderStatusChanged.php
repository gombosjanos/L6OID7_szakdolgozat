<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Munkalap;

class WorkorderStatusChanged extends Notification
{
    use Queueable;

    public function __construct(public Munkalap $munkalap, public string $from, public string $to) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $id = $this->munkalap->ID;
        return (new MailMessage)
            ->subject('Munkalap státusz változás')
            ->greeting('Tisztelt Ügyfelünk!')
            ->line("A #{$id} munkalap státusza megváltozott: {$this->from} → {$this->to}.")
            ->line('További részletekért jelentkezzen be a felületre.');
    }
}

