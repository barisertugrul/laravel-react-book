<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vitrinde bir planı vurgulamak ve etiketini yazmak yönetimin kararı (Bölüm 10).
 * Bölüm 7'de her ürünün İKİNCİ planı sabit olarak "En çok tercih edilen" gösteriliyordu.
 * Boş: vurgu yok. Geriye uyumlu: mevcut planlar etiketsiz kalır (geri dönüşte eski kod bu sütunu görmez).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('badge', 40)->nullable()->after('max_sites');
        });
    }

    public function down(): void
    {
        Schema::table('plans', fn (Blueprint $table) => $table->dropColumn('badge'));
    }
};
