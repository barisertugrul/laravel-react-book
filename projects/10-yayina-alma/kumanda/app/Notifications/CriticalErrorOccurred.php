<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * Kuyruğa girmez (ShouldQueue YOK): hata kuyruktan kaynaklanıyorsa e-posta da takılırdı.
 * Yığın izi gönderilmez; e-postaya gizli veri sızmasın, ayrıntılar logda.
 */
class CriticalErrorOccurred extends Notification
{
    public function __construct(
        public string $class,
        public string $message,
        public string $location,
        public string $context,
        public ?int $userId,
        public string $environment,
        public string $version,
    ) {}

    public static function fromThrowable(Throwable $e): self
    {
        // Eşleşmiş bir rota varsa hata bir HTTP isteğinde oldu; yoksa komut ya da kuyruk işi
        $context = request()->route() !== null
            ? request()->method().' '.request()->fullUrl()
            : 'Komut: '.implode(' ', array_slice($_SERVER['argv'] ?? [], 0, 4));

        $versionFile = base_path('VERSION');

        return new self(
            class: get_class($e),
            message: Str::limit($e->getMessage(), 500),
            location: Str::after($e->getFile(), base_path().DIRECTORY_SEPARATOR).':'.$e->getLine(),
            context: $context,
            userId: auth()->id(),
            environment: app()->environment(),
            version: is_file($versionFile) ? trim((string) file_get_contents($versionFile)) : 'bilinmiyor',
        );
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject('🚨 '.config('app.name').': '.class_basename($this->class))
            ->greeting('Uygulamada kritik hata')
            ->line("**{$this->class}**")
            ->line($this->message)
            ->line("Yer: {$this->location}")
            ->line("İstek: {$this->context}")
            ->line('Kullanıcı: '.($this->userId ?? 'oturum yok'))
            ->line("Ortam: {$this->environment} · Sürüm: {$this->version}")
            ->line('Aynı hata '.config('monitoring.alert_throttle_minutes').' dakika boyunca yeniden e-posta göndermez; ayrıntılar storage/logs altında.');
    }
}
