<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Notifications\OrderShipped;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => Role::Admin]);
    }

    public function test_admin_creates_a_product_with_turkish_price_and_generated_slug(): void
    {
        $this->actingAs($this->admin)->post(route('admin.products.store'), [
            'name' => 'Kosta Rika Tarrazú 250 g',
            'price' => '489,90',
            'stock' => 7,
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $product = Product::sole();
        $this->assertSame(48990, $product->price);
        $this->assertSame('kosta-rika-tarrazu-250-g', $product->slug);
    }

    public function test_admin_ships_a_paid_order_and_customer_is_notified(): void
    {
        Notification::fake();
        $order = Order::factory()->create(['status' => OrderStatus::Paid]);

        $this->actingAs($this->admin)->patch(route('admin.orders.ship', $order), ['tracking_number' => 'YK123'])
            ->assertSessionHasNoErrors();

        $this->assertSame(OrderStatus::Shipped, $order->fresh()->status);
        Notification::assertSentTo($order->user, OrderShipped::class);
    }

    public function test_unpaid_order_cannot_be_shipped(): void
    {
        $order = Order::factory()->create();

        $this->actingAs($this->admin)->patch(route('admin.orders.ship', $order), ['tracking_number' => 'YK123'])->assertStatus(422);
    }
}
