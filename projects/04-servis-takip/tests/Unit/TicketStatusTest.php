<?php

namespace Tests\Unit;

use App\Enums\TicketStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TicketStatusTest extends TestCase
{
    public static function allowed(): array
    {
        return [
            'tespit' => [TicketStatus::Received, TicketStatus::Diagnosing],
            'onay bekle' => [TicketStatus::Diagnosing, TicketStatus::WaitingApproval],
            'hazır' => [TicketStatus::Repairing, TicketStatus::Ready],
            'iade' => [TicketStatus::Cancelled, TicketStatus::Delivered],
            'testte sorun: tamire geri' => [TicketStatus::Ready, TicketStatus::Repairing],
        ];
    }

    public static function forbidden(): array
    {
        return [
            'adım atlama' => [TicketStatus::Received, TicketStatus::Ready],
            'geri gitme' => [TicketStatus::Repairing, TicketStatus::Diagnosing],
            'kapanmış kayıt' => [TicketStatus::Delivered, TicketStatus::Received],
            'tamirdeyken iptal' => [TicketStatus::Repairing, TicketStatus::Cancelled],
        ];
    }

    #[DataProvider('allowed')]
    public function test_allowed_transitions(TicketStatus $from, TicketStatus $to): void
    {
        $this->assertTrue($from->canTransitionTo($to));
    }

    #[DataProvider('forbidden')]
    public function test_forbidden_transitions(TicketStatus $from, TicketStatus $to): void
    {
        $this->assertFalse($from->canTransitionTo($to));
    }

    public function test_every_status_is_reachable_from_received(): void
    {
        // Durum makinesinde "ölü" (hiçbir yoldan ulaşılamayan) durum kalmamalı
        $reached = [TicketStatus::Received];
        for ($i = 0; $i < count($reached); $i++) {
            foreach ($reached[$i]->transitions() as $next) {
                if (! in_array($next, $reached, true)) {
                    $reached[] = $next;
                }
            }
        }

        $this->assertEqualsCanonicalizing(TicketStatus::cases(), $reached);
    }
}
