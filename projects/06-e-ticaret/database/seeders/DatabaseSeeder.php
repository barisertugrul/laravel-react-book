<?php

namespace Database\Seeders;

use App\Actions\MarkOrderPaid;
use App\Actions\PlaceOrder;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Product;
use App\Models\User;
use App\Support\Cart;
use App\Support\Money;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create(['name' => 'Test User', 'email' => 'test@example.com', 'role' => Role::Admin]);
        $customer = User::factory()->create(['name' => 'Elif Şahin', 'email' => 'musteri@example.com']);

        $products = [
            ['Etiyopya Yirgacheffe 250 g', '459,90', 24, 'Çiçeksi ve narenciye notaları; açık kavrum. Filtre kahve için ideal.'],
            ['Kolombiya Huila 250 g', '419,90', 30, 'Karamel ve kırmızı elma; orta kavrum.'],
            ['Brezilya Cerrado 250 g', '379,90', 40, 'Fındık ve sütlü çikolata; espresso harmanlarının temeli.'],
            ['Kenya AA 250 g', '529,90', 8, 'Frenk üzümü ve greyfurt; parlak asidite.'],
            ['Guatemala Antigua 250 g', '449,90', 15, 'Kakao ve baharat; dengeli gövde.'],
            ['Espresso Harmanı 1 kg', '1.249,90', 12, 'Brezilya ve Kolombiya harmanı; koyu kavrum.'],
            ['Kosta Rika Tarrazú 250 g', '489,90', 2, 'Bal ve şeftali; az sayıda kaldı.'],
            ['Kafeinsiz Kolombiya 250 g', '439,90', 0, 'Swiss Water yöntemiyle kafeinsizleştirilmiş.'],
            ['V60 Filtre Kâğıdı (100 adet)', '189,90', 60, 'Beyaz, 02 boy.'],
            ['El Değirmeni', '1.890,00', 5, 'Ayarlanabilir çelik bıçaklı.'],
            ['Cam Demlik 600 ml', '749,90', 10, 'Isıya dayanıklı borosilikat cam.'],
            ['Kahve Ölçü Kaşığı', '79,90', 100, 'Paslanmaz çelik, 10 g.'],
        ];

        foreach ($products as [$name, $price, $stock, $description]) {
            Product::create(['name' => $name, 'price' => Money::parse($price), 'stock' => $stock, 'description' => $description]);
        }

        // Örnek siparişler: gerçek akıştan geçirerek (stok düşer, numara verilir)
        $place = new PlaceOrder;
        $paid = new MarkOrderPaid;
        $address = [
            'shipping_name' => 'Elif Şahin',
            'shipping_phone' => '0532 555 12 34',
            'shipping_address' => 'Moda Cad. No: 12 Daire: 4 Kadıköy',
            'shipping_city' => 'İstanbul',
        ];

        foreach ([[1, 2], [3, 6], [2, 9, 12], [5]] as $i => $productIds) {
            $cart = new Cart(app('session.store'));
            foreach ($productIds as $id) {
                $cart->add(Product::find($id), $id === 9 ? 2 : 1);
            }
            $order = $place->handle($customer, $cart, $address, 'fake');

            if ($i < 3) {
                $paid->handle($order, $order->total, 'FAKE-SEED'.$i);
            }
            if ($i === 0) {
                $order->update(['status' => OrderStatus::Shipped, 'shipped_at' => now(), 'tracking_number' => 'YK123456789']);
            }
        }
    }
}
