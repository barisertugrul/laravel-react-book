<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Tutarlar Bölüm 5'teki gibi kuruş cinsinden tam sayı. */
return new class extends Migration
{
    public function up(): void
    {
        // Kayıt olan herkes müşteri olur; yönetici yalnızca komut satırından açılır
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('customer')->after('email');
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price');           // KDV dahil satış fiyatı
            $table->unsignedInteger('stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->nullable()->unique(); // SIP-000042, kayıttan sonra id'den üretilir
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('shipping');
            $table->unsignedBigInteger('total');
            $table->string('shipping_name');
            $table->string('shipping_phone');
            $table->string('shipping_address');
            $table->string('shipping_city');
            $table->string('payment_provider');
            $table->string('payment_reference')->nullable(); // sağlayıcıdaki ödeme numarası
            $table->timestamp('expires_at');                 // bu saate kadar ödenmezse iptal
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->string('tracking_number')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // Ürün ileride silinse de sipariş kaydı bozulmasın
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            // Sipariş anındaki ad ve fiyatın KOPYASI: ürün fiyatı sonradan değişse de sipariş değişmez
            $table->string('product_name');
            $table->unsignedBigInteger('unit_price');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('total');
        });

        // Ödeme sağlayıcısından gelen bildirimlerin kaydı.
        // event_id benzersiz: aynı bildirim iki kez gelirse ikincisi işlenmez.
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('event_id');
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->json('payload');
            $table->timestamps();

            $table->unique(['provider', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('products');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('role'));
    }
};
