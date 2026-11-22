<?php

namespace Database\Seeders;

use App\Actions\CancelInvoice;
use App\Actions\CreateInvoice;
use App\Actions\RecordPayment;
use App\Enums\ContactType;
use App\Models\Contact;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::factory()->create(['name' => 'Test User', 'email' => 'test@example.com']);

        $customers = collect([
            ['Yıldız Mimarlık Ltd. Şti.', '4810293746', 'Kadıköy'],
            ['Deniz Kafe', '7723019485', 'Beşiktaş'],
            ['Akın Tekstil A.Ş.', '0192837465', 'Bursa'],
            ['Selin Arslan', '28471930562', null],
            ['Mavi Diş Polikliniği', '6650192834', 'Üsküdar'],
        ])->map(fn ($c) => Contact::create([
            'type' => ContactType::Customer, 'name' => $c[0], 'tax_number' => $c[1], 'tax_office' => $c[2],
            'email' => 'muhasebe@example.com', 'phone' => '0532 '.rand(100, 999).' '.rand(10, 99).' '.rand(10, 99),
        ]));

        $suppliers = collect([
            ['Bulut Barındırma Hizmetleri', '3319204857', 'Şişli'],
            ['Ofis Kırtasiye', '5502918374', 'Kadıköy'],
            ['Hızlı Kargo A.Ş.', '9081726354', 'Ataşehir'],
        ])->map(fn ($s) => Contact::create([
            'type' => ContactType::Supplier, 'name' => $s[0], 'tax_number' => $s[1], 'tax_office' => $s[2],
        ]));

        $services = [
            ['Web sitesi tasarımı', '1', '18500', 20],
            ['Aylık bakım ve destek', '1', '2750', 20],
            ['Logo ve kurumsal kimlik', '1', '9000', 20],
            ['Sosyal medya yönetimi', '1', '6500', 20],
            ['Fotoğraf çekimi (saat)', '3', '1250', 20],
            ['Eğitim seti (kitap)', '5', '349,90', 0],
        ];
        $purchases = [
            ['Sunucu kiralama', '1', '1850', 20],
            ['A4 fotokopi kâğıdı (koli)', '4', '689,90', 20],
            ['Kargo gönderimi', '12', '74,50', 20],
        ];

        $create = new CreateInvoice;
        $pay = new RecordPayment;
        $today = CarbonImmutable::today();

        // Son on iki ayda, eskiden yeniye: numaralar tarih sırasıyla verilsin
        $plan = [];
        foreach (range(11, 0) as $monthsAgo) {
            foreach (range(1, rand(2, 4)) as $i) {
                $plan[] = ['sales', $today->subMonths($monthsAgo)->startOfMonth()->addDays(rand(0, 25))];
            }
            $plan[] = ['purchase', $today->subMonths($monthsAgo)->startOfMonth()->addDays(rand(0, 25))];
        }
        usort($plan, fn ($a, $b) => $a[1] <=> $b[1]);

        foreach ($plan as $n => [$type, $date]) {
            if ($date->isAfter($today)) {
                $date = $today;
            }
            $source = $type === 'sales' ? $services : $purchases;
            $lines = collect($source)->shuffle()->take(rand(1, 3))->map(fn ($l) => [
                'description' => $l[0],
                'quantity' => $l[1],
                'unit_price' => Money::parse($l[2]),
                'discount_rate' => rand(0, 3) === 0 ? 10 : 0,
                'vat_rate' => $l[3],
            ])->values()->all();

            $invoice = $create->handle([
                'type' => $type,
                'contact_id' => ($type === 'sales' ? $customers : $suppliers)->random()->id,
                'issue_date' => $date->toDateString(),
                'due_date' => $date->addDays(30)->toDateString(),
            ], $lines);

            // Bir faturayı iptal edelim: listede ve PDF'te "İptal" görünsün
            if ($n === 7) {
                (new CancelInvoice)->handle($invoice, 'Yanlış cariye kesildi, yeniden düzenlenecek.');

                continue;
            }

            // Eski faturaların çoğu ödenmiş, bir kısmı kısmen ödenmiş, yenilerin çoğu açık
            $age = $date->diffInDays($today);
            $roll = rand(1, 10);
            if ($type === 'sales' && $n % 5 === 3 && $age > 10) {
                // Her beş faturadan birinde yarı ödeme: "Kısmen ödendi" durumu da görünsün
                $pay->handle($invoice, intdiv($invoice->total, 2), $date->addDays(7)->min($today)->toDateString(), 'cash', $user, 'Kalanı ay sonunda');
            } elseif ($age > 45 && $roll <= 7 || $age > 15 && $roll <= 3) {
                $pay->handle($invoice, $invoice->total, $date->addDays(rand(5, min(40, max(5, (int) $age))))->min($today)->toDateString(), 'bank_transfer', $user);
            }
        }
    }
}
