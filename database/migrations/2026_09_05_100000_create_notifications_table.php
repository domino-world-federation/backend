<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notifikasi dalam aplikasi — isi lonceng di topbar.
 *
 * Bentuk bawaan Laravel apa adanya, dan itu disengaja: `DatabaseNotification`
 * beserta relasi `notifications()`/`unreadNotifications()` di `Notifiable`
 * sudah menuliskan seluruh perilakunya, dan tabel bikinan sendiri berarti
 * menulis ulang semuanya untuk nol perbedaan yang terlihat pengguna.
 *
 * Kunci utamanya UUID, bukan auto-increment: id-nya muncul di URL saat sebuah
 * notifikasi ditandai terbaca, dan nomor berurutan di sana memberi tahu siapa
 * pun berapa banyak notifikasi yang pernah dikirim seluruh backoffice ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Lonceng SELALU menanyakan hal yang sama: yang belum dibaca milik
            // satu orang, terbaru dulu. Tanpa indeks ini pertanyaan itu memindai
            // seluruh tabel di tiap permintaan halaman — dan lonceng ada di
            // setiap halaman.
            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
