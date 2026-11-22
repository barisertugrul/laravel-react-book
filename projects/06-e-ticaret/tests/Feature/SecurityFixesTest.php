<?php

namespace Tests\Feature;

use App\Actions\PlaceOrder;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Bölüm sonundaki "Güvenlik incelemesi"nin testleri */
class SecurityFixesTest extends TestCase
{
    use RefreshDatabase;

    private array $address = [
        'shipping_name' => 'Elif Şahin',
        'shipping_phone' => '0532 555 12 34',
        'shipping_address' => 'Moda Cad. No: 12',
        'shipping_city' => 'İstanbul',
    ];

    /** 1. hata: ödeme dönüş adresine güvenmek */
    public function test_visiting_the_return_page_does_not_mark_an_order_paid(): void
    {
        $order = Order::factory()->create();

        // İlk sürümde bu adres siparişi "ödendi" yapıyordu
        $this->actingAs($order->user)->get(route('checkout.result', $order).'?status=success')->assertOk();

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_unsigned_webhook_is_rejected(): void
    {
        $order = Order::factory()->create();

        $this->postJson(route('webhooks.fake'), [
            'event_id' => 'x', 'type' => 'payment.succeeded', 'order' => $order->number, 'amount' => $order->total, 'reference' => 'R',
        ])->assertForbidden();

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    /** 2. hata: stoğu "oku, kontrol et, yaz" diye düşmek */
    public function test_read_check_write_oversells_but_conditional_decrement_does_not(): void
    {
        // İlk sürümün yöntemi: iki istek ürünü aynı anda okur
        $product = Product::factory()->create(['stock' => 1]);
        $requestA = Product::find($product->id);
        $requestB = Product::find($product->id);

        foreach ([$requestA, $requestB] as $copy) {
            if ($copy->stock >= 1) {          // ikisi de "stok var" görür
                $copy->stock = $copy->stock - 1;
                $copy->save();                // ikisi de stock = 0 yazar
            }
        }
        $this->assertSame(0, $product->fresh()->stock); // 1 ürün, 2 satış — fark edilmeden!

        // Düzeltilmiş yöntem: koşullu tek sorgu.
        // fresh(): bellekteki $product hâlâ stock = 1 sanıyor; update(['stock' => 1]) "değişiklik yok"
        // deyip sorgu göndermezdi. Önce veritabanındaki güncel hali okuyoruz.
        $product->fresh()->update(['stock' => 1]);
        $first = Product::whereKey($product->id)->where('stock', '>=', 1)->decrement('stock', 1);
        $second = Product::whereKey($product->id)->where('stock', '>=', 1)->decrement('stock', 1);

        $this->assertSame(1, $first);
        $this->assertSame(0, $second); // ikinci istek ürünü alamaz
        $this->assertSame(0, $product->fresh()->stock);
    }

    public function test_two_customers_cannot_buy_the_last_item(): void
    {
        $product = Product::factory()->create(['stock' => 1]);
        $cart = new Cart(app('session.store'));

        // Birinci müşteri son ürünü alır
        $cart->add($product, 1);
        (new PlaceOrder)->handle(User::factory()->create(), $cart, $this->address, 'fake');

        // İkinci müşteri ürünü, stok varken sepetine eklemişti
        session()->put('cart', [$product->id => 1]);

        $this->expectException(ValidationException::class);
        (new PlaceOrder)->handle(User::factory()->create(), $cart, $this->address, 'fake');
    }

    /** Kayıt açık; ama kayıt formundan yönetici olunamaz */
    public function test_registration_cannot_grant_admin_role(): void
    {
        $this->post('/register', [
            'name' => 'Kurnaz Müşteri',
            'email' => 'kurnaz@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'admin',
        ]);

        $this->assertSame(Role::Customer, User::where('email', 'kurnaz@example.com')->sole()->role);
    }
}
