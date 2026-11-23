<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionPaymentFailed extends Notification
{
    use Queueable;

    public function __construct(public Subscription $subscription, public string $reason) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Kumanda: abonelik ödemesi alınamadı')
            ->line("{$this->subscription->plan()->name} planının aylık ödemesi alınamadı: {$this->reason}")
            ->line('Planınız '.$this->subscription->grace_ends_at?->timezone(config('app.timezone'))->format('d.m.Y').' tarihine kadar sürüyor; her gün yeniden deneyeceğiz.')
            ->action('Kartı güncelle', route('billing.index'));
    }
}
