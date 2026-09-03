<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            // Layar "Manage Category" (`433:6116`) menyusun kategori dalam
            // urutan yang bisa diatur, jadi urutannya disimpan — bukan
            // diturunkan dari abjad atau id.
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'position']);
        });

        Schema::create('news_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();

            // Tiga rasio berbeda dari satu berita — layar Add News (`252:4480`)
            // meminta hero, potret, dan lanskap terpisah. Disimpan sebagai path
            // relatif di disk `public`, bukan URL.
            $table->string('hero_image_path')->nullable();
            $table->string('portrait_image_path')->nullable();
            $table->string('landscape_image_path')->nullable();

            $table->boolean('is_highlighted')->default(false);

            // draft | scheduled | published. Disimpan sebagai string, bukan enum
            // Postgres: menambah status baru nanti tidak perlu ALTER TYPE.
            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index('is_highlighted');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_articles');
        Schema::dropIfExists('news_categories');
    }
};
