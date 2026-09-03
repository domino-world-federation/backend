<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Undangan admin — panel "Invitation Flow" (`529:9714`).
 *
 * "No password is created in this form. The admin receives a secure invitation
 * link. Invitation expires after 72 hours and can be resent or revoked from
 * Admin Users."
 *
 * ── Yang disimpan adalah HASH tokennya, bukan tokennya. ──
 *
 * Token mentah hanya pernah ada dua kali: di dalam tautan yang dikirim, dan di
 * memori saat seseorang membukanya. Menyimpannya apa adanya berarti siapa pun
 * yang bisa membaca tabel ini — dump database, backup, layar admin yang keliru
 * — bisa menerima undangan orang lain dan memilih sandi akun itu. Perlakuannya
 * sama dengan token reset sandi bawaan Laravel, dan alasannya sama.
 *
 * Tidak dihapus setelah dipakai. `accepted_at` dan `revoked_at` membuat baris
 * ini jadi catatan: siapa mengundang siapa, kapan, dan apakah undangannya
 * pernah sampai. Undangan yang menghilang begitu diterima menghapus separuh
 * cerita tentang bagaimana sebuah akun admin lahir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // SHA-256 heksadesimal, 64 karakter.
            $table->string('token_hash', 64)->unique();

            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Query terpanasnya: "undangan yang masih berlaku untuk pengguna
            // ini" — dipakai daftar admin untuk memutuskan apakah tombol
            // Resend dan Revoke digambar.
            $table->index(['user_id', 'accepted_at', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_invitations');
    }
};
