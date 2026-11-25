<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_lists_active_products_with_their_plans(): void
    {
        Plan::factory()->for(Product::factory()->create(['name' => 'Ajans Asistanı']))->count(3)->create();
        Product::factory()->create(['is_active' => false]);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('shop/index')
            ->has('products', 1)
            ->where('products.0.name', 'Ajans Asistanı')
            ->has('products.0.plans', 3));
    }
}
