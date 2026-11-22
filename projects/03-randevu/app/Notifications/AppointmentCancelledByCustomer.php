<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Müşteri randevusunu iptal ettiğinde işletme çalışanlarına giden e-posta. */
class AppointmentCancelledByCustomer extends Notification implements ShouldQueue
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
            ->subject("İptal: {$a->customer_name}, ".$a->starts_at->format('d.m H:i'))
            ->line("{$a->customer_name} ({$a->customer_phone}), ".$a->starts_at->locale('tr')->isoFormat('D MMMM dddd, HH:mm')." tarihindeki {$a->service->name} randevusunu iptal etti.")
            ->line('Bu saat yeniden randevuya açıldı.')
            ->action('Takvimi aç', route('calendar', ['week' => $a->starts_at->startOfWeek()->toDateString()]));
    }
}
