<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_lists_only_active_products(): void
    {
        Product::factory()->create(['name' => 'Kenya AA']);
        Product::factory()->create(['name' => 'Eski Harman', 'is_active' => false]);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('shop/index')
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Kenya AA'));
    }

    public function test_inactive_product_page_is_not_found(): void
    {
        $product = Product::factory()->create(['is_active' => false]);

        $this->get(route('products.show', $product))->assertNotFound();
    }
}
