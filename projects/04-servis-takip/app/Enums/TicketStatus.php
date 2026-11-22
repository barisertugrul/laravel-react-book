<?php

namespace App\Enums;

/**
 * Servis kaydının yaşam döngüsü: basit bir durum makinesi (state machine).
 * Her durum, kendisinden hangi durumlara geçilebileceğini bilir.
 *
 *   received → diagnosing → waiting_approval → repairing ⇄ ready → delivered
 *        └────────┴──────────────┴──→ cancelled ──→ delivered (cihaz iade)
 *
 * ready → repairing: son testte sorun çıkarsa cihaz tamire geri döner (gerekçe zorunlu).
 * Bu bir "geri alma" değil, iş akışının meşru bir adımıdır.
 */
enum TicketStatus: string
{
    case Received = 'received';
    case Diagnosing = 'diagnosing';
    case WaitingApproval = 'waiting_approval';
    case Repairing = 'repairing';
    case Ready = 'ready';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Teslim alındı',
            self::Diagnosing => 'Arıza tespiti',
            self::WaitingApproval => 'Onay bekleniyor',
            self::Repairing => 'Tamir ediliyor',
            self::Ready => 'Teslime hazır',
            self::Delivered => 'Teslim edildi',
            self::Cancelled => 'İptal edildi',
        };
    }

    /** @return list<self> */
    public function transitions(): array
    {
        return match ($this) {
            self::Received => [self::Diagnosing, self::Cancelled],
            self::Diagnosing => [self::WaitingApproval, self::Repairing, self::Cancelled],
            self::WaitingApproval => [self::Repairing, self::Cancelled],
            self::Repairing => [self::Ready],
            self::Ready => [self::Delivered, self::Repairing],
            self::Cancelled => [self::Delivered],
            self::Delivered => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->transitions(), true);
    }

    /** Bu geçiş için not (gerekçe) yazmak zorunlu mu? */
    public function requiresReason(self $next): bool
    {
        return $next === self::Cancelled || ($this === self::Ready && $next === self::Repairing);
    }

    /** Arayüzde onay sorulacak, sonucu müşteriyi etkileyen geçişler. */
    public function needsConfirmation(): bool
    {
        return in_array($this, [self::Ready, self::Delivered, self::Cancelled], true);
    }

    /** Müşteriye e-posta ile haber verilecek durumlar. */
    public function notifiesCustomer(): bool
    {
        return in_array($this, [self::WaitingApproval, self::Ready, self::Cancelled], true);
    }

    /** Henüz kapanmamış, üzerinde çalışılan kayıtlar. */
    public function isOpen(): bool
    {
        return ! in_array($this, [self::Delivered, self::Cancelled], true);
    }

    /** React'e gönderilecek tüm durumlar: değer ve etiket. */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
