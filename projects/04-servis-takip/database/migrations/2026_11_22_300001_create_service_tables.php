<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('technician')->after('email');
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->unique(); // müşteriyi telefonundan tanıyoruz
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->unique();          // SRV-000042: insanlar için
            $table->string('tracking_code')->unique();             // tahmin edilemez: bağlantılar için
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('device_type');
            $table->string('brand');
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->text('problem');
            $table->string('accessories')->nullable();
            $table->decimal('estimated_cost', 10, 2)->nullable();
            $table->decimal('final_cost', 10, 2)->nullable();
            $table->string('status')->default('received')->index();
            $table->timestamps();
        });

        // Durum geçmişi: kim, ne zaman, neyi neye çevirdi
        Schema::create('ticket_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('note')->nullable();
            $table->boolean('is_public')->default(true); // müşteri takip sayfasında görsün mü
            // Bu olay başka bir olayı geri alıyorsa, geri alınan olayın id'si
            $table->foreignId('reverts_event_id')->nullable()->constrained('ticket_events')->nullOnDelete();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_events');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('customers');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('role'));
    }
};
