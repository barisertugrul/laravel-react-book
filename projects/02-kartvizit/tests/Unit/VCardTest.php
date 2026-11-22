<?php

namespace Tests\Unit;

use App\Models\Card;
use App\Support\VCard;
use Tests\TestCase;

class VCardTest extends TestCase
{
    public function test_name_is_split_into_first_and_last(): void
    {
        $this->assertSame(['Ali Rıza', 'Yılmaz'], VCard::splitName('Ali Rıza Yılmaz'));
        $this->assertSame(['Cem', ''], VCard::splitName('Cem'));
    }

    public function test_special_characters_are_escaped(): void
    {
        $this->assertSame('Demir\, Kaya \; Ortakları', VCard::escape('Demir, Kaya ; Ortakları'));
    }

    public function test_card_is_converted_to_vcard(): void
    {
        // Veritabanına yazmadan, bellekte bir kart oluşturuyoruz.
        $card = new Card([
            'full_name' => 'Ayşe Demir',
            'company' => 'Demir Tasarım',
            'phone' => '+90 532 123 45 67',
        ]);
        $card->setRelation('links', collect());

        $vcard = VCard::fromCard($card);

        $this->assertStringStartsWith("BEGIN:VCARD\r\nVERSION:3.0\r\n", $vcard);
        $this->assertStringContainsString("N:Demir;Ayşe;;;\r\n", $vcard);
        $this->assertStringContainsString("TEL;TYPE=CELL:+90 532 123 45 67\r\n", $vcard);
        $this->assertStringNotContainsString('EMAIL', $vcard);
        $this->assertStringEndsWith("END:VCARD\r\n", $vcard);
    }
}
