<?php

namespace Tests\Feature;

use App\Enums\Plan;
use App\Models\Card;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardTest extends TestCase
{
    use RefreshDatabase;

    private function validData(array $overrides = []): array
    {
        return [
            'full_name' => 'Barış Ertuğrul',
            'title' => 'Yazılım Geliştirici',
            'theme' => 'klasik',
            'is_published' => true,
            'links' => [
                ['label' => 'GitHub', 'url' => 'https://github.com/ornek'],
                ['label' => 'LinkedIn', 'url' => 'https://linkedin.com/in/ornek'],
            ],
            ...$overrides,
        ];
    }

    public function test_slug_is_generated_from_name_with_turkish_characters(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('cards.store'), $this->validData())
            ->assertRedirect(route('cards.index'));

        $card = $user->cards()->first();
        $this->assertSame('baris-ertugrul', $card->slug);
        $this->assertSame(['GitHub', 'LinkedIn'], $card->links->pluck('label')->all());
    }

    public function test_duplicate_names_get_numbered_slugs(): void
    {
        Card::factory()->create(['slug' => 'baris-ertugrul']);

        $this->assertSame('baris-ertugrul-2', Card::uniqueSlug('Barış Ertuğrul'));
    }

    public function test_free_plan_allows_only_one_card(): void
    {
        $user = User::factory()->create();
        Card::factory()->for($user)->create();

        $this->actingAs($user)->post(route('cards.store'), $this->validData())
            ->assertForbidden();

        $this->assertSame(1, $user->cards()->count());
    }

    public function test_pro_plan_allows_more_cards(): void
    {
        $user = User::factory()->create(['plan' => Plan::Pro]);
        Card::factory()->for($user)->create();

        $this->actingAs($user)->post(route('cards.store'), $this->validData());

        $this->assertSame(2, $user->cards()->count());
    }

    public function test_free_plan_cannot_use_pro_themes(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('cards.store'), $this->validData(['theme' => 'gece']))
            ->assertSessionHasErrors(['theme' => 'Seçtiğiniz tema planınızda yok.']);
    }

    public function test_reserved_slugs_are_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('cards.store'), $this->validData(['slug' => 'admin']))
            ->assertSessionHasErrors('slug');
    }

    public function test_link_url_must_be_valid(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('cards.store'), $this->validData([
                'links' => [['label' => 'Site', 'url' => 'ornek.com']],
            ]))
            ->assertSessionHasErrors('links.0.url');
    }

    public function test_users_cannot_edit_someone_elses_card(): void
    {
        $card = Card::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('cards.edit', $card))
            ->assertForbidden();
    }

    public function test_update_replaces_links(): void
    {
        $card = Card::factory()->create();
        $card->links()->create(['label' => 'Eski', 'url' => 'https://eski.com']);

        $this->actingAs($card->user)->put(route('cards.update', $card), $this->validData([
            'slug' => $card->slug,
            'links' => [['label' => 'Yeni', 'url' => 'https://yeni.com']],
        ]));

        $this->assertSame(['Yeni'], $card->fresh()->links->pluck('label')->all());
    }
}
