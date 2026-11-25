<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Panelin paket kütüphanesi: ajansın kendi (ör. ücretli) eklentileri, zip olarak
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 100);   // eklenti klasörünün adı: "ajans-asistani"
            $table->string('name', 150);   // eklenti başlığındaki "Plugin Name"
            $table->string('version', 30); // "Version"
            $table->string('path');        // storage/app/private içinde; herkese açık değil
            $table->char('sha256', 64);    // sitenin indirdiği dosyayı doğrulaması için
            $table->unsignedInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['agency_id', 'slug', 'version']);
        });

        // Toplu işlem: "Contact Form 7'yi şu 12 sitede güncelle"
        Schema::create('operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);  // install | update | license
            $table->string('label', 200); // listede görünen ad: "Contact Form 7 kur (wordpress.org)"
            $table->text('params');      // komutun ayrıntıları (şifreli): kaynak, slug, paket, dosya, lisans anahtarı
            $table->uuid('batch_id')->nullable(); // Laravel'in job_batches tablosundaki toplu iş
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['agency_id', 'created_at']);
        });

        // İşlemin her sitedeki adımı: ilerleme ekranındaki her satır
        Schema::create('operation_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending'); // pending | running | succeeded | failed | cancelled
            $table->string('message', 500)->nullable(); // hata ya da sonuç özeti
            $table->json('result')->nullable();          // eklentinin yanıtı: dosya, ad, sürüm, etkin mi
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['operation_id', 'site_id']); // bir işlemde bir site bir kez
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_targets');
        Schema::dropIfExists('operations');
        Schema::dropIfExists('packages');
    }
};
