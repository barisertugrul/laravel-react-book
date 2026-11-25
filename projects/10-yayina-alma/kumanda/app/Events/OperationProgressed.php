<?php

namespace App\Events;

use App\Models\Operation;
use App\Models\OperationTarget;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Toplu işlemde bir site ilerledi (ya da işlem bitti). İlerleme ekranı bunu dinler.
 *
 * ShouldBroadcastNow: yayın, olayı oluşturan kuyruk işinin içinde hemen yapılır.
 * ShouldBroadcast olsaydı yayın İKİNCİ bir kuyruk işi olurdu; yoğun bir kuyrukta
 * "bitti" haberi, işin kendisinden saniyeler sonra gelirdi.
 */
class OperationProgressed implements ShouldBroadcastNow
{
    public function __construct(public Operation $operation, public ?OperationTarget $target = null) {}

    /** Ajansın özel kanalı: kimin dinleyebileceğine routes/channels.php karar verir */
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("agencies.{$this->operation->agency_id}");
    }

    public function broadcastAs(): string
    {
        return 'operation.progressed';
    }

    /**
     * Yayına yalnızca ekranın gösterdiği alanlar girer. Model olduğu gibi gönderilseydi
     * ilişkiler ve ileride eklenecek her sütun da kanala sızardı.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'operation_id' => $this->operation->id,
            'finished_at' => $this->operation->finished_at?->toIso8601String(),
            'summary' => $this->operation->summary(),
            'target' => $this->target?->toRow(),
        ];
    }
}
