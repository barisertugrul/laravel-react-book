<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kiracı (tenant): her ajans kendi sitelerini ve ekibini görür
        Schema::create('agencies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->timestamps();
        });

        // Bir kullanıcı birden çok ajansta olabilir (ör. serbest çalışan bir geliştirici)
        Schema::create('agency_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20); // owner | member
            $table->timestamps();

            $table->unique(['agency_id', 'user_id']);
        });

        // Kullanıcının o an çalıştığı ajans (ajans değiştirici bunu günceller)
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_agency_id')->nullable()->after('email')->constrained('agencies')->nullOnDelete();
        });

        Schema::create('agency_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role', 20);
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            // Aynı kişiye ikinci davet yeni satır açmaz, eskisini yeniler
            $table->unique(['agency_id', 'email']);
        });

        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('url');      // ziyaretçinin gördüğü adres (home_url)
            $table->string('api_url');  // WordPress REST kökü (rest_url)

            // Eşleştirmede değiş tokuş edilen anahtarlar (base64)
            $table->string('agent_public_key', 64); // eklentinin açık anahtarı: yanıtlarını doğrularız
            $table->text('panel_secret_key');       // bu siteye özel gizli anahtarımız (şifreli saklanır)

            // Son kontrolün özeti: listeler her seferinde site_checks tablosuna inmesin
            $table->string('health_status', 20)->default('unknown');
            $table->timestamp('status_changed_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('wp_version', 20)->nullable();
            $table->string('php_version', 20)->nullable();
            $table->string('agent_version', 20)->nullable();
            $table->unsignedSmallInteger('pending_updates')->default(0);
            $table->unsignedInteger('response_ms')->nullable();
            $table->timestamp('ssl_expires_at')->nullable();
            $table->timestamps();

            $table->unique(['agency_id', 'url']);
        });

        Schema::create('site_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('response_ms')->nullable();
            $table->timestamp('ssl_expires_at')->nullable();
            $table->json('issues');            // [{level, code, message}]
            $table->json('report')->nullable(); // ajanın gönderdiği ham rapor
            $table->string('error')->nullable();
            $table->timestamp('checked_at');

            $table->index(['site_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_checks');
        Schema::dropIfExists('sites');
        Schema::dropIfExists('agency_invitations');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_agency_id');
        });
        Schema::dropIfExists('agency_user');
        Schema::dropIfExists('agencies');
    }
};
