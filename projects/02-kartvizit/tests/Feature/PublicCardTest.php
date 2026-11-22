<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_card_is_visible_and_counts_views(): void
    {
        $card = Card::factory()->create(['slug' => 'ayse-demir']);

        $this->get('/k/ayse-demir')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public-card')
                ->where('card.full_name', $card->full_name));

        $this->assertSame(1, $card->fresh()->view_count);
    }

    public function test_owner_views_are_not_counted(): void
    {
        $card = Card::factory()->create();

        $this->actingAs($card->user)->get(route('cards.public', $card->slug))->assertOk();

        $this->assertSame(0, $card->fresh()->view_count);
    }

    public function test_unpublished_card_is_hidden_from_visitors_but_not_owner(): void
    {
        $card = Card::factory()->unpublished()->create();

        $this->get(route('cards.public', $card->slug))->assertNotFound();
        $this->actingAs($card->user)->get(route('cards.public', $card->slug))->assertOk();
    }

    public function test_vcard_can_be_downloaded(): void
    {
        $card = Card::factory()->create(['full_name' => 'Ayşe Demir', 'slug' => 'ayse-demir']);

        $response = $this->get(route('cards.vcard', $card->slug))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/vcard; charset=utf-8')
            ->assertDownload('ayse-demir.vcf');

        $this->assertStringContainsString("FN:Ayşe Demir\r\n", $response->getContent());
    }
}
