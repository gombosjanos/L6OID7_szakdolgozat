<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Munkalap;

class WorkorderCreated extends Notification
{
    use Queueable;

    public function __construct(public Munkalap $munkalap) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $id = $this->munkalap->ID;
        return (new MailMessage)
            ->subject('Új munkalap létrehozva')
            ->greeting('Tisztelt Ügyfelünk!')
            ->line("Új munkalap készült azonosítóval: #{$id}.")
            ->line('Állapot: ' . ($this->munkalap->statusz ?? 'uj'))
            ->line('Köszönjük a türelmét.');
    }
}

