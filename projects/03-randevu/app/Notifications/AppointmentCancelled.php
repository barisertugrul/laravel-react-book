<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** İşletme bir randevuyu iptal ettiğinde müşteriye giden e-posta. */
class AppointmentCancelled extends Notification implements ShouldQueue
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
            ->subject('Randevunuz iptal edildi')
            ->greeting("Merhaba {$a->customer_name},")
            ->line('Üzgünüz, '.$a->starts_at->locale('tr')->isoFormat('D MMMM dddd, HH:mm')." tarihindeki {$a->service->name} randevunuz işletme tarafından iptal edildi.")
            ->action('Yeni randevu al', route('home'))
            ->salutation(config('app.name'));
    }
}
