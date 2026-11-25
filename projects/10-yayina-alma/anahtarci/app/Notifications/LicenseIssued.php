<?php

namespace App\Notifications;

use App\Models\License;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LicenseIssued extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public License $license) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $license = $this->license->loadMissing('plan.product');
        $sites = $license->isUnlimited() ? 'sınırsız sayıda sitede' : "{$license->max_sites} sitede";

        return (new MailMessage)
            ->subject("Lisansınız hazır: {$license->plan->product->name}")
            ->greeting("Merhaba {$notifiable->name},")
            ->line("{$license->plan->product->name} ({$license->plan->name}) lisansınız {$sites} kullanılabilir.")
            ->line('Lisans anahtarınız:')
            ->line($license->key)
            ->line('Eklentiyi kurduktan sonra WordPress panelinde Ayarlar → Lisans sayfasına bu anahtarı yapıştırın.')
            ->line('Güncelleme ve destek hakkınız '.$license->updates_until->translatedFormat('j F Y').' tarihine kadar sürer.')
            ->action('Lisansı görüntüle', route('licenses.show', $license));
    }
}
