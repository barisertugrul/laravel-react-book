<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** ShouldQueue: bildirim istek sırasında değil, kuyruk işçisi tarafından gönderilir. */
class AppointmentBooked extends Notification implements ShouldQueue
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
            ->subject('Randevunuz alındı: '.$a->starts_at->locale('tr')->isoFormat('D MMMM dddd, HH:mm'))
            ->greeting("Merhaba {$a->customer_name},")
            ->line('Randevunuz oluşturuldu. Sizi bekliyoruz!')
            ->line("**Hizmet:** {$a->service->name} ({$a->service->duration} dk)")
            ->line('**Tarih:** '.$a->starts_at->locale('tr')->isoFormat('D MMMM YYYY dddd'))
            ->line('**Saat:** '.$a->starts_at->format('H:i').' - '.$a->ends_at->format('H:i'))
            ->action('Randevuyu iptal et', $a->cancelUrl())
            ->line('Gelemeyecekseniz lütfen yukarıdaki bağlantıyla iptal edin; saat başka bir müşteriye açılsın.')
            ->salutation(config('app.name'));
    }
}
