<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppointmentReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Appointment $appointment) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $a = $this->appointment->loadMissing('service');

        return (new MailMessage)
            ->subject('Yarın görüşüyoruz: '.$a->starts_at->format('H:i'))
            ->greeting("Merhaba {$a->customer_name},")
            ->line("Yarın saat **{$a->starts_at->format('H:i')}** için {$a->service->name} randevunuzu hatırlatmak istedik.")
            ->action('Randevuyu iptal et', $a->cancelUrl())
            ->salutation(config('app.name'));
    }
}
