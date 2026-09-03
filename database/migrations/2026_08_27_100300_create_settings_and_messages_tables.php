<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Privacy Policy dan Terms & Conditions (`258:8086`, `258:8144`) punya
        // bentuk yang sama persis: tanggal, slug, lalu blok judul+deskripsi yang
        // bisa ditambah, dinonaktifkan, dan dihapus. Satu tabel dengan kolom
        // `key`, bukan dua tabel kembar.
        Schema::create('legal_pages', function (Blueprint $table) {
            $table->id();
            $table->string('key', 32)->unique();  // privacy-policy | terms
            $table->string('title');
            $table->string('slug')->unique();
            $table->date('last_updated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('legal_page_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_page_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['legal_page_id', 'position']);
        });

        // Contact & Social (`258:8202`) adalah formulir pengaturan, bukan
        // daftar. Disimpan sebagai pasangan kunci-nilai supaya menambah satu
        // kolom sosial media baru tidak perlu migrasi.
        Schema::create('site_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('country')->nullable();
            // "Media Requests", "General Enquiries", "Partnerships",
            // "Membership Information" (`258:8271`).
            $table->string('topic', 64)->nullable();
            $table->string('subject');
            $table->text('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['read_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('site_settings');
        Schema::dropIfExists('legal_page_blocks');
        Schema::dropIfExists('legal_pages');
    }
};
