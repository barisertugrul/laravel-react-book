<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_is_public_and_shows_only_available_products(): void
    {
        $category = Category::factory()->create(['name' => 'Tatlılar']);
        Product::factory()->for($category)->create(['name' => 'Sütlaç']);
        Product::factory()->for($category)->unavailable()->create(['name' => 'Kazandibi']);

        $this->get(route('menu'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('menu')
                ->has('categories', 1)
                ->has('categories.0.products', 1)
                ->where('categories.0.products.0.name', 'Sütlaç'));
    }

    public function test_inactive_and_empty_categories_are_hidden(): void
    {
        Product::factory()->for(Category::factory()->inactive())->create();
        Category::factory()->create(); // ürünü yok

        $this->get(route('menu'))
            ->assertInertia(fn (Assert $page) => $page->has('categories', 0));
    }
}
