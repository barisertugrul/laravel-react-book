<?php

namespace App\Notifications;

use App\Billing\Plan;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionEnded extends Notification
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
        $free = Plan::free();

        return (new MailMessage)
            ->subject('Kumanda: aboneliğiniz sona erdi')
            ->line($this->reason)
            ->line("Ajansınız {$free->name} planına geçti ({$free->maxSites} site). Sitelerinizin izlenmesi sürüyor; sınırın üstündeyseniz toplu işlemler kapalıdır.")
            ->action('Planları gör', route('billing.index'));
    }
}
