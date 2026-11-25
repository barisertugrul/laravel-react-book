<?php

namespace App\Notifications;

use App\Enums\HealthStatus;
use App\Models\Site;
use App\Models\SiteCheck;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SiteStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Site $site, public HealthStatus $previous, public SiteCheck $check) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $status = $this->check->status;
        $better = $status->severity() < $this->previous->severity();

        $mail = (new MailMessage)
            ->subject(($better ? '✅ ' : '⚠️ ')."{$this->site->name}: {$status->label()}")
            ->greeting($better ? 'Site düzeldi' : 'Sitede sorun var')
            ->line("{$this->site->name} ({$this->site->url}) durumu: {$this->previous->label()} → {$status->label()}.");

        foreach (array_slice($this->check->issues, 0, 5) as $issue) {
            $mail->line('• '.$issue['message']);
        }

        return $mail->action('Siteyi panelde aç', route('sites.show', $this->site));
    }
}
