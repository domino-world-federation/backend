<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Layar Privacy Policy (`258:8086`) menulis "Last Modified · John Doe" tepat di
 * bawah judulnya.
 *
 * Halaman hukum tidak punya penulis — ia sudah ada sejak seeder dan hanya
 * diubah. Jadi yang perlu disimpan bukan `author_id` melainkan siapa yang
 * TERAKHIR menyentuhnya; itu pula satu-satunya nama yang berguna di layar yang
 * isinya harus tepat secara hukum.
 *
 * `nullOnDelete`, sama seperti di News: menghapus akun tidak boleh ikut
 * menghapus halamannya. Yang hilang cuma namanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_pages', function (Blueprint $table) {
            $table->foreignId('updated_by_id')
                ->nullable()
                ->after('last_updated_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('legal_pages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by_id');
        });
    }
};
