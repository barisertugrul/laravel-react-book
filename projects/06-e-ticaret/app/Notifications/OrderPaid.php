<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPaid extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Siparişiniz alındı: {$this->order->number}")
            ->greeting("Merhaba {$notifiable->name},")
            ->line('Ödemeniz onaylandı, siparişinizi hazırlamaya başladık.');

        foreach ($this->order->items as $item) {
            $mail->line("{$item->quantity} × {$item->product_name} — ".Money::format($item->total));
        }

        return $mail
            ->line('Toplam: '.Money::format($this->order->total))
            ->action('Siparişi görüntüle', route('orders.show', $this->order));
    }
}
