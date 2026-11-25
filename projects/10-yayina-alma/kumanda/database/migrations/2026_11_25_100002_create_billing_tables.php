<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ajans başına TEK satır: abonelik bitip yeniden başlasa da aynı satır güncellenir
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('plan', 30);
            $table->string('status', 20); // active | past_due | ended
            $table->string('provider', 20);
            $table->text('card_token');            // sağlayıcının kayıtlı kart jetonu (şifreli); kart numarası BİZDE YOK
            $table->char('card_last4', 4)->nullable();
            $table->timestamp('current_period_ends_at');
            $table->timestamp('grace_ends_at')->nullable(); // ödeme alınamadıysa: bu ana kadar ücretli plan sürer
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('canceled_at')->nullable();   // iptal istendi: dönem sonunda biter, yenilenmez
            $table->timestamps();
        });

        // Her tahsilat denemesi: başarılı ya da değil (müşteriye "neden düştüm" sorusunun cevabı)
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('plan', 30);
            $table->unsignedInteger('amount'); // kuruş
            $table->string('status', 20);      // paid | failed
            $table->string('reference', 100)->nullable()->unique(); // sağlayıcının işlem numarası
            $table->string('failure_message')->nullable();
            $table->timestamp('created_at');
        });

        // Sağlayıcı bildirimleri: aynı olay ikinci kez işlenmez (Bölüm 6'daki payment_events)
        Schema::create('billing_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20);
            $table->string('event_id', 100);
            $table->string('type', 50);
            $table->json('payload');
            $table->timestamp('created_at');

            $table->unique(['provider', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_events');
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('subscriptions');
    }
};
