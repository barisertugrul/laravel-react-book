<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private array $address = [
        'shipping_name' => 'Elif Şahin',
        'shipping_phone' => '0532 555 12 34',
        'shipping_address' => 'Moda Cad. No: 12',
        'shipping_city' => 'İstanbul',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = User::factory()->create();
    }

    private function addToCart(Product $product, int $quantity): void
    {
        $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => $quantity]);
    }

    public function test_guests_must_log_in_to_check_out(): void
    {
        $this->get(route('checkout.create'))->assertRedirect(route('login'));
    }

    public function test_order_is_created_stock_is_reserved_and_customer_is_sent_to_payment(): void
    {
        $product = Product::factory()->create(['name' => 'Kenya AA', 'price' => 52990, 'stock' => 5]);
        $this->actingAs($this->customer);
        $this->addToCart($product, 2);

        $response = $this->post(route('checkout.store'), $this->address);

        $order = Order::sole();
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame('SIP-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT), $order->number);
        $this->assertSame(105980, $order->subtotal);
        $this->assertSame(0, $order->shipping); // 750 ₺ üzeri
        $this->assertSame(3, $product->fresh()->stock);
        $this->assertSame('Kenya AA', $order->items->first()->product_name);
        $this->assertSame([], session('cart', []));

        // Sahte sağlayıcının imzalı ödeme sayfasına yönlendirildi
        $this->assertStringContainsString('/sahte-odeme/'.$order->number, $response->headers->get('Location'));
        $this->assertStringContainsString('signature=', $response->headers->get('Location'));
    }

    public function test_order_keeps_the_price_at_the_time_of_purchase(): void
    {
        $product = Product::factory()->create(['price' => 10000]);
        $this->actingAs($this->customer);
        $this->addToCart($product, 1);
        $this->post(route('checkout.store'), $this->address);

        $product->update(['price' => 20000]);

        $this->assertSame(10000, Order::sole()->items->first()->unit_price);
    }

    public function test_checkout_fails_without_touching_stock_when_any_item_ran_out(): void
    {
        $a = Product::factory()->create(['stock' => 5]);
        $b = Product::factory()->create(['stock' => 2]);
        $this->actingAs($this->customer);
        $this->addToCart($a, 1);
        $this->addToCart($b, 2);

        $b->update(['stock' => 1]); // bu arada başka biri bir tane aldı

        $this->post(route('checkout.store'), $this->address)->assertSessionHasErrors('cart');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(5, $a->fresh()->stock); // ilk satırda düşülen stok geri alındı
        $this->assertSame(1, $b->fresh()->stock);
    }

    public function test_unpaid_orders_expire_and_release_stock(): void
    {
        $product = Product::factory()->create(['stock' => 5]);
        $this->actingAs($this->customer);
        $this->addToCart($product, 2);
        $this->post(route('checkout.store'), $this->address);

        $this->travel(31)->minutes();
        $this->artisan('orders:expire')->assertSuccessful();

        $this->assertSame(OrderStatus::Cancelled, Order::sole()->status);
        $this->assertSame(5, $product->fresh()->stock);

        // İkinci kez çalışması stoğu tekrar artırmaz
        $this->artisan('orders:expire');
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_customers_cannot_see_each_others_orders(): void
    {
        $order = Order::factory()->create();

        $this->actingAs($this->customer)->get(route('orders.show', $order))->assertNotFound();
        $this->get(route('checkout.result', $order))->assertNotFound();
    }
}
