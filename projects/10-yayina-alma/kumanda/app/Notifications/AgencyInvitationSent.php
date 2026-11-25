<?php

namespace App\Notifications;

use App\Models\AgencyInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class AgencyInvitationSent extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public AgencyInvitation $invitation) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $agency = $this->invitation->agency;

        return (new MailMessage)
            ->subject("{$agency->name} sizi Kumanda'ya davet ediyor")
            ->line(($this->invitation->inviter?->name ?? 'Ajans sahibi')." sizi {$agency->name} ekibine {$this->invitation->role->label()} olarak davet etti.")
            ->line('Hesabınız yoksa bağlantıdan sonra kayıt olabilirsiniz; aynı e-posta adresini kullanın.')
            ->action('Daveti görüntüle', URL::temporarySignedRoute('invitations.show', $this->invitation->expires_at, $this->invitation))
            ->line('Bağlantı '.$this->invitation->expires_at->translatedFormat('j F Y').' tarihine kadar geçerlidir.');
    }
}
