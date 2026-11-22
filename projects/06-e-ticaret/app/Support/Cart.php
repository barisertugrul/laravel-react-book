<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/**
 * Oturumda (session) tutulan alışveriş sepeti.
 *
 * Oturumda YALNIZCA ürün id'si ve adet durur: [3 => 2, 7 => 1].
 * Fiyat, ad ve stok her seferinde veritabanından okunur; sepetteki fiyat
 * kullanıcının tarayıcısından gelen bir bilgiye asla dayanmaz.
 */
class Cart
{
    private const KEY = 'cart';

    public function __construct(private Session $session) {}

    /** @return array<int, int> ürün id'si => adet */
    public function items(): array
    {
        return $this->session->get(self::KEY, []);
    }

    public function add(Product $product, int $quantity = 1): void
    {
        $items = $this->items();
        $this->set($product, ($items[$product->id] ?? 0) + $quantity);
    }

    /** Adedi ayarlar; stoktan fazlası istenirse stok kadarını koyar */
    public function set(Product $product, int $quantity): void
    {
        $items = $this->items();
        $quantity = min($quantity, $product->stock);

        if ($quantity < 1) {
            unset($items[$product->id]);
        } else {
            $items[$product->id] = $quantity;
        }

        $this->session->put(self::KEY, $items);
    }

    public function remove(Product $product): void
    {
        $items = $this->items();
        unset($items[$product->id]);
        $this->session->put(self::KEY, $items);
    }

    public function clear(): void
    {
        $this->session->forget(self::KEY);
    }

    /** Sepetteki toplam ürün adedi (üst menüdeki rozet için) */
    public function count(): int
    {
        return array_sum($this->items());
    }

    /**
     * Sepet satırları, güncel fiyat ve stokla.
     * Satıştan kaldırılmış ürünler sessizce düşer.
     *
     * @return Collection<int, array{product: Product, quantity: int, total: int, in_stock: bool}>
     */
    public function lines(): Collection
    {
        $items = $this->items();

        return Product::visible()
            ->whereKey(array_keys($items))
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => [
                'product' => $product,
                'quantity' => $items[$product->id],
                'total' => $product->price * $items[$product->id],
                'in_stock' => $product->stock >= $items[$product->id],
            ]);
    }

    /** @return array{subtotal: int, shipping: int, total: int} */
    public static function totals(int $subtotal): array
    {
        $shipping = $subtotal === 0 || $subtotal >= config('shop.free_shipping_from') ? 0 : config('shop.shipping_fee');

        return ['subtotal' => $subtotal, 'shipping' => $shipping, 'total' => $subtotal + $shipping];
    }
}
