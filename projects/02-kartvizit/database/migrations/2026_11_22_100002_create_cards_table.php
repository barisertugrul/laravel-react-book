<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('full_name');
            $table->string('title')->nullable();
            $table->string('company')->nullable();
            $table->text('bio')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('address')->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('theme')->default('klasik');
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamps();
        });

        Schema::create('card_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('url');
            $table->unsignedInteger('sort_order')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_links');
        Schema::dropIfExists('cards');
    }
};
