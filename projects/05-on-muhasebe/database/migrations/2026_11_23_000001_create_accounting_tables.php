<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bütün para sütunları kuruş cinsinden tam sayıdır (unsignedBigInteger / bigInteger).
 * 1.250,50 TL veritabanında 125050 olarak durur.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Cari hesaplar: müşteriler ve tedarikçiler
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // customer | supplier
            $table->string('name');
            $table->string('tax_number', 11)->nullable(); // VKN (10) veya TCKN (11)
            $table->string('tax_office')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('type');                 // sales | purchase
            $table->string('number')->unique();     // SAT-2026-0001
            $table->foreignId('contact_id')->constrained()->restrictOnDelete();
            $table->date('issue_date');
            $table->date('due_date');
            $table->unsignedBigInteger('subtotal');        // iskonto öncesi
            $table->unsignedBigInteger('discount_total');
            $table->unsignedBigInteger('vat_total');
            $table->unsignedBigInteger('total');
            // Ödenen tutar, ödemelerin toplamının bir kopyasıdır (bkz. "Bilinçli bir kopya")
            $table->unsignedBigInteger('paid_total')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();

            $table->index(['type', 'due_date']);
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantity', 12, 3);
            $table->unsignedBigInteger('unit_price');
            $table->unsignedTinyInteger('discount_rate')->default(0); // %
            $table->unsignedTinyInteger('vat_rate');                   // %
            $table->unsignedBigInteger('net');   // iskonto sonrası matrah
            $table->unsignedBigInteger('vat');
            $table->unsignedBigInteger('total');
            $table->unsignedSmallInteger('sort_order')->default(0);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            // Ödeme kaydı olan fatura silinemez: muhasebe kaydı silinmez, iptal edilir
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('paid_at');
            $table->unsignedBigInteger('amount');
            $table->string('method');
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('contacts');
    }
};
