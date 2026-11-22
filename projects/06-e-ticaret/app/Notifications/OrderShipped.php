<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderShipped extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Siparişiniz kargoda: {$this->order->number}")
            ->greeting("Merhaba {$notifiable->name},")
            ->line('Siparişiniz kargoya verildi.')
            ->line("Kargo takip numarası: {$this->order->tracking_number}")
            ->action('Siparişi görüntüle', route('orders.show', $this->order));
    }
}
