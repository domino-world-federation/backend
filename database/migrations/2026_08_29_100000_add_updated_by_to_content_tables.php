<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom "Last Modified" disamakan untuk seluruh modul isi.
 *
 * News dan Legal Pages sudah punya `updated_by_id` lebih dulu; tiga sisanya
 * menyusul supaya tiap daftar bisa menjawab pertanyaan yang sama —
 * "siapa yang terakhir mengubah ini" — dengan cara yang sama.
 *
 * `nullOnDelete` di semuanya: menghapus akun tidak boleh ikut menghapus isinya.
 * Yang hilang cuma namanya, dan itu memang jawaban yang jujur.
 */
return new class extends Migration
{
    /** Tabel yang belum punya kolomnya. News dan Legal sudah dapat lebih dulu. */
    private const TABLES = ['faqs', 'press_releases', 'gallery_items'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('updated_by_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('updated_by_id');
            });
        }
    }
};
