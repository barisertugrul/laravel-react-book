<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Bölüm 2'nin "Güvenlik incelemesi": adres değişince eski bağlantılar bozulmasın */
class SlugHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_address_redirects_to_the_new_one(): void
    {
        $card = Card::factory()->create(['slug' => 'ayse-demir']);
        $card->update(['slug' => 'ayse-demir-tasarim']);

        $this->get('/k/ayse-demir')->assertRedirect('/k/ayse-demir-tasarim')->assertStatus(301);
        $this->get('/k/ayse-demir/vcard')->assertRedirect('/k/ayse-demir-tasarim/vcard');
        $this->get('/k/hic-olmayan')->assertNotFound();
    }

    public function test_another_card_cannot_take_an_old_address(): void
    {
        Card::factory()->create(['slug' => 'ayse-demir'])->update(['slug' => 'ayse-demir-tasarim']);

        // Başka bir kullanıcı eski adresi almaya çalışıyor: o adresteki QR kodlar artık ona gitmemeli
        $this->actingAs(User::factory()->create())->post(route('cards.store'), [
            'full_name' => 'Ayşe Demir',
            'slug' => 'ayse-demir',
            'theme' => 'klasik',
        ])->assertSessionHasErrors('slug');

        // Otomatik üretilen adres de eski adresi atlar
        $this->assertSame('ayse-demir-2', Card::uniqueSlug('Ayşe Demir'));
    }

    public function test_card_can_return_to_its_own_old_address(): void
    {
        $card = Card::factory()->create(['slug' => 'ayse-demir']);
        $card->update(['slug' => 'ayse-demir-tasarim']);

        $this->actingAs($card->user)->put(route('cards.update', $card), [
            'full_name' => $card->full_name,
            'slug' => 'ayse-demir',
            'theme' => 'klasik',
        ])->assertSessionHasNoErrors();

        $this->assertSame('ayse-demir', $card->fresh()->slug);
        $this->assertSame(['ayse-demir-tasarim'], $card->slugHistories()->pluck('slug')->all());
    }
}
