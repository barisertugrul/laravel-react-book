<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MenuSeeder extends Seeder
{
    /**
     * Örnek bir kafe menüsü oluşturur.
     * Görseller, ürünün baş harfini içeren küçük SVG dosyaları olarak üretilir.
     */
    public function run(): void
    {
        $menu = [
            ['Kahvaltı', '#f59e0b', [
                ['Serpme Kahvaltı (2 kişilik)', 'Peynir çeşitleri, zeytin, bal-kaymak, reçel, sıcaklar ve sınırsız çay.', 640],
                ['Menemen', 'Domates, biber ve köy yumurtası; isteğe göre peynirli.', 185],
                ['Sucuklu Yumurta', 'Bakır sahanda kasap sucuk ve iki yumurta.', 210],
            ]],
            ['Ana Yemekler', '#ef4444', [
                ['Izgara Köfte', 'Közlenmiş biber, domates ve pilav ile.', 340],
                ['Tavuk Şiş', 'Yoğurtlu marinasyon, lavaş ve mevsim salatası.', 310],
                ['Mantı', 'Sarımsaklı yoğurt ve tereyağlı sos.', 260],
            ]],
            ['Tatlılar', '#ec4899', [
                ['Fıstıklı Baklava', 'Dört dilim, Antep fıstığı ile.', 220],
                ['Sütlaç', 'Fırında, tarçın ile servis edilir.', 120],
            ]],
            ['İçecekler', '#0ea5e9', [
                ['Türk Kahvesi', 'Lokum ile servis edilir.', 90],
                ['Taze Portakal Suyu', 'Günlük sıkım, 330 ml.', 110],
                ['Ayran', 'Köpüklü yayık ayranı.', 50],
                ['Limonata', 'Ev yapımı, naneli.', 95, false],
            ]],
        ];

        foreach ($menu as $categoryOrder => [$categoryName, $color, $products]) {
            $category = Category::create([
                'name' => $categoryName,
                'sort_order' => $categoryOrder,
            ]);

            foreach ($products as $productOrder => $item) {
                [$name, $description, $price] = $item;

                $category->products()->create([
                    'name' => $name,
                    'description' => $description,
                    'price' => $price,
                    'is_available' => $item[3] ?? true,
                    'sort_order' => $productOrder,
                    'image_path' => $this->placeholderImage($name, $color),
                ]);
            }
        }
    }

    private function placeholderImage(string $name, string $color): string
    {
        $initial = mb_strtoupper(mb_substr($name, 0, 1));

        $path = 'products/seed-'.Str::slug($name).'.svg';

        $svg = <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300">
          <defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="{$color}" stop-opacity="0.25"/>
            <stop offset="1" stop-color="{$color}" stop-opacity="0.6"/>
          </linearGradient></defs>
          <rect width="400" height="300" fill="url(#g)"/>
          <circle cx="200" cy="150" r="82" fill="#ffffff" fill-opacity="0.85"/>
          <text x="200" y="150" dy="0.35em" font-family="Georgia, serif" font-size="100" font-weight="bold" fill="{$color}" text-anchor="middle">{$initial}</text>
        </svg>
        SVG;

        Storage::disk('public')->put($path, $svg);

        return $path;
    }
}
