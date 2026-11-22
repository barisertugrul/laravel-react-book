<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kartın eski adresleri. Adres değiştiğinde eski adres buraya yazılır;
 * eski adrese gelen ziyaretçi (ör. basılı bir QR koddan) yeni adrese yönlendirilir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_slug_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_slug_histories');
    }
};
