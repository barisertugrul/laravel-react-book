<?php

namespace App\Notifications;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  TicketStatus  $status  Bildirimin hangi durum için oluşturulduğu
     */
    public function __construct(public Ticket $ticket, public TicketStatus $status, public ?string $note = null) {}

    /**
     * Kuyruk işçisi e-postayı göndermeden hemen önce çağrılır.
     * Bu arada durum değiştiyse (ör. teknisyen geri aldıysa) e-posta gönderilmez.
     */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        return $this->ticket->fresh()?->status === $this->status;
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $t = $this->ticket;

        $mail = (new MailMessage)
            ->subject("{$t->code}: {$t->status->label()}")
            ->greeting("Merhaba {$notifiable->name},")
            ->line("{$t->brand} {$t->model} cihazınızın durumu güncellendi: **{$t->status->label()}**");

        $mail = match ($t->status) {
            TicketStatus::WaitingApproval => $mail->line('Tahmini ücret: '.self::tl($t->estimated_cost).'. Onayınız için lütfen bizi arayın.'),
            TicketStatus::Ready => $mail->line('Cihazınızı teslim alabilirsiniz. Ücret: '.self::tl($t->final_cost)),
            default => $mail,
        };

        if ($this->note) {
            $mail->line("Not: {$this->note}");
        }

        return $mail
            ->action('Servis durumunu görüntüle', $t->tracking_url)
            ->salutation(config('app.name'));
    }

    /** 1250.5 → "1.250,50 ₺" */
    public static function tl(?string $amount): string
    {
        return number_format((float) $amount, 2, ',', '.').' ₺';
    }
}
