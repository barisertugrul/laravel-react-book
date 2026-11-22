<?php

namespace Database\Seeders;

use App\Enums\CardTheme;
use App\Enums\Plan;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Ücretsiz planda, tek kartı olan kullanıcı
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $card = $user->cards()->create([
            'slug' => 'ayse-demir',
            'full_name' => 'Ayşe Demir',
            'title' => 'Kurucu & UX Tasarımcı',
            'company' => 'Demir Tasarım Stüdyosu',
            'bio' => 'Küçük işletmeler için sade ve kullanışlı dijital ürünler tasarlıyorum.',
            'phone' => '+90 532 123 45 67',
            'email' => 'ayse@ornek.com',
            'website' => 'https://ornek.com',
            'address' => 'Kadıköy, İstanbul',
            'theme' => CardTheme::Klasik,
        ]);
        $card->links()->createMany([
            ['label' => 'LinkedIn', 'url' => 'https://linkedin.com/in/ornek', 'sort_order' => 0],
            ['label' => 'Instagram', 'url' => 'https://instagram.com/ornek', 'sort_order' => 1],
            ['label' => 'Portfolyo', 'url' => 'https://ornek.com/portfolyo', 'sort_order' => 2],
        ]);

        // Pro planda, temaları gösterebilmek için iki kartı olan kullanıcı
        $pro = User::factory()->create([
            'name' => 'Pro User',
            'email' => 'pro@example.com',
            'plan' => Plan::Pro,
        ]);
        $pro->cards()->create([
            'slug' => 'mert-kaya',
            'full_name' => 'Mert Kaya',
            'title' => 'Yazılım Geliştirici',
            'company' => 'Kaya Yazılım',
            'bio' => 'Laravel ve React ile SaaS ürünleri geliştiriyorum.',
            'phone' => '+90 555 987 65 43',
            'email' => 'mert@ornek.com',
            'website' => 'https://ornek.dev',
            'theme' => CardTheme::Gece,
        ])->links()->createMany([
            ['label' => 'GitHub', 'url' => 'https://github.com/ornek', 'sort_order' => 0],
            ['label' => 'X', 'url' => 'https://x.com/ornek', 'sort_order' => 1],
        ]);
        $pro->cards()->create([
            'slug' => 'kaya-yazilim',
            'full_name' => 'Kaya Yazılım',
            'title' => 'Web ve Mobil Uygulama Geliştirme',
            'phone' => '+90 216 000 00 00',
            'email' => 'iletisim@ornek.dev',
            'website' => 'https://ornek.dev',
            'address' => 'Ataşehir, İstanbul',
            'theme' => CardTheme::Okyanus,
        ]);
    }
}
