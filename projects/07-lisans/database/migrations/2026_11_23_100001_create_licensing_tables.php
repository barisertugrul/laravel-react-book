<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kayıt olan herkes müşteri olur; yönetici yalnızca komut satırından açılır (Bölüm 6)
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('customer')->after('email');
        });

        // Satılan yazılımlar (WordPress eklentileri)
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();   // anahtarın içine yazılır: eklenti kendi ürününü tanır
            $table->string('summary');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Bir ürünün satış seçenekleri: 1 site, 5 site, sınırsız
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('price');                   // kuruş
            $table->unsignedSmallInteger('max_sites')->nullable(); // null: sınırsız
            $table->unsignedTinyInteger('sort')->default(0);
            $table->timestamps();
        });

        // Sepet yok: her sipariş tek bir plan içindir
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->nullable()->unique(); // LIS-000001, kayıttan sonra id'den türetilir
            $table->foreignId('user_id')->constrained();
            $table->foreignId('plan_id')->constrained();
            $table->string('status')->default('pending');
            $table->unsignedInteger('total');                // kuruş, satın alma anındaki fiyat
            $table->string('payment_provider');
            $table->string('payment_reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        // Ödeme bildirimlerinin kaydı: aynı bildirim iki kez işlenemez (Bölüm 6)
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('event_id');
            $table->foreignId('order_id')->nullable()->constrained();
            $table->string('type');
            $table->json('payload');
            $table->timestamps();

            $table->unique(['provider', 'event_id']);
        });

        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            // unique: bir siparişten EN FAZLA bir lisans çıkar (webhook iki kez gelse bile)
            $table->foreignId('order_id')->nullable()->unique()->constrained();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('plan_id')->constrained();
            $table->string('key')->nullable()->unique();    // imzalı anahtar; id gerektiği için kayıttan sonra yazılır
            $table->string('status')->default('active');
            // Satın alma anındaki sınır kopyalanır: plan sonradan değişse de müşterinin hakkı değişmez
            $table->unsignedSmallInteger('max_sites')->nullable();
            $table->date('updates_until');                  // bu tarihten sonra eklenti çalışır, güncelleme alamaz
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoke_reason')->nullable();
            $table->timestamps();
        });

        // Lisansın kullanıldığı siteler. Her aktivasyonun kendi Sanctum token'ı vardır.
        Schema::create('activations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->string('domain');
            $table->string('ip', 45)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            // Aynı site aynı lisansta ikinci kez yer kaplamaz
            $table->unique(['license_id', 'domain']);
        });

        // Lisansın geçmişi: kim, ne zaman, hangi siteden ne yaptı (denetim kaydı)
        Schema::create('license_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('domain')->nullable();
            $table->string('ip', 45)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_events');
        Schema::dropIfExists('activations');
        Schema::dropIfExists('licenses');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('products');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('role'));
    }
};
