<?php

namespace App\Domain\Reminders\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Aviso interno (campana). Canal "database" por ahora; correo o WhatsApp se agregan en via() más adelante.
 * El contenido se arma una sola vez (ReminderMessage) y se guarda tal cual: no depende de datos que cambien después.
 */
class BillingReminder extends Notification
{
    /**
     * @param  array{kind: string, tone: string, title: string, body: string, url: string, target_date: string}  $payload
     */
    public function __construct(private readonly array $payload) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return $this->payload;
    }
}
