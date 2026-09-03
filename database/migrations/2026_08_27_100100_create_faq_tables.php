<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faq_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faq_category_id')->constrained()->restrictOnDelete();

            $table->string('question');
            $table->longText('answer');

            // "Apply to Page" (`341:4861`) — satu FAQ bisa tampil di beberapa
            // halaman, maksimal tiga pertanyaan per halaman. Disimpan sebagai
            // JSON daripada tabel pivot: nilainya daftar tetap dari tiga
            // halaman yang tidak punya atribut sendiri, jadi tabel penghubung
            // hanya menambah join tanpa menambah apa pun yang bisa disimpan.
            $table->json('pages')->default('[]');

            $table->boolean('is_active')->default(true);
            // Layar "Manage Order" (`343:4961`) menyeret FAQ ke urutan tertentu.
            $table->unsignedInteger('position')->default(0);

            $table->timestamps();

            $table->index(['is_active', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('faq_categories');
    }
};
