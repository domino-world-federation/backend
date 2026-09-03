<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tiga tabel untuk tiga formulir di situs publik yang selama ini buntu.
 *
 * Yang keempat — formulir Kontak — sudah punya tabelnya (`contact_messages`)
 * sejak modul Contact Messages dibangun; yang belum cuma endpointnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();

            // Unik: mendaftar dua kali bukan galat, dan menyimpannya dua kali
            // berarti orang itu menerima tiap kiriman dua kali.
            $table->string('email')->unique();

            // Berhenti berlangganan MENANDAI, bukan menghapus. Baris yang
            // hilang berarti alamat yang sama bisa didaftarkan ulang oleh
            // siapa pun — termasuk oleh orang yang baru saja keluar.
            $table->timestamp('unsubscribed_at')->nullable();

            $table->timestamps();
            $table->index('unsubscribed_at');
        });

        Schema::create('tournament_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->timestamps();

            // Satu alamat, satu turnamen, satu baris. Menekan "Notify me" dua
            // kali adalah hal yang wajar dilakukan orang saat ragu apakah yang
            // pertama tercatat.
            $table->unique(['tournament_id', 'email']);
        });

        /*
         * Laporan integritas — SENGAJA tanpa satu pun kolom identitas.
         *
         * Tidak ada nama, tidak ada email, dan TIDAK ADA alamat IP. Halaman
         * yang mengirimnya berjanji "identitas Anda dirahasiakan", dan alamat
         * IP adalah identitas: menyimpannya berarti janji itu bergantung pada
         * siapa yang kebetulan punya akses database, bukan pada bentuk datanya.
         *
         * Konsekuensinya diterima: laporan tidak bisa ditanya balik, dan
         * penyalahgunaan hanya ditahan throttle (yang memakai IP sesaat di
         * memori, tidak menyimpannya). Itu harga saluran anonim, dan saluran
         * anonim memang yang dijanjikan halamannya.
         */
        Schema::create('integrity_reports', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->text('description');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index('read_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrity_reports');
        Schema::dropIfExists('tournament_notifications');
        Schema::dropIfExists('newsletter_subscribers');
    }
};
