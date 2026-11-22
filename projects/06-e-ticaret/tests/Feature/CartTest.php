<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Support\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_add_to_cart_and_price_comes_from_the_database(): void
    {
        $product = Product::factory()->create(['price' => 29990, 'stock' => 10]);

        // İstekte bir "price" alanı olsa bile dikkate alınmaz
        $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 2, 'price' => 1])
            ->assertSessionHasNoErrors();

        $this->get(route('cart.index'))->assertInertia(fn (Assert $page) => $page
            ->component('shop/cart')
            ->where('lines.0.quantity', 2)
            ->where('lines.0.total', 59980)
            ->where('totals.shipping', 5990)  // 750 ₺'nin altında: kargo ücretli
            ->where('totals.total', 65970));
    }

    public function test_quantity_is_limited_to_stock(): void
    {
        $product = Product::factory()->create(['stock' => 3]);

        $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 5]);

        $this->assertSame([$product->id => 3], session('cart'));
    }

    public function test_sold_out_and_inactive_products_cannot_be_added(): void
    {
        $soldOut = Product::factory()->create(['stock' => 0]);
        $inactive = Product::factory()->create(['is_active' => false]);

        $this->post(route('cart.store'), ['product_id' => $soldOut->id, 'quantity' => 1])->assertSessionHasErrors('quantity');
        $this->post(route('cart.store'), ['product_id' => $inactive->id, 'quantity' => 1])->assertNotFound();
        $this->assertEmpty(session('cart', []));
    }

    public function test_quantity_can_be_changed_and_line_removed(): void
    {
        $product = Product::factory()->create();
        $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1]);

        $this->patch(route('cart.update', $product->id), ['quantity' => 4]);
        $this->assertSame([$product->id => 4], session('cart'));

        $this->delete(route('cart.destroy', $product->id));
        $this->assertSame([], session('cart'));
    }

    public function test_free_shipping_threshold(): void
    {
        $this->assertSame(5990, Cart::totals(74999)['shipping']);
        $this->assertSame(0, Cart::totals(75000)['shipping']);
        $this->assertSame(0, Cart::totals(0)['shipping']);
    }
}
