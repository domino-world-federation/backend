<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Formulir kontak situs publik tidak menanyakan subjek.
 *
 * Layar CMS-nya menggambar kolom Subject (`258:8271`) dan modul ini dibangun
 * dari layar itu, jadi kolomnya lahir `NOT NULL` — sebelum ada satu pun
 * pengirim sungguhan. Yang sebenarnya ditanyakan formulirnya adalah TOPIK, dan
 * itu kolom yang berbeda.
 *
 * Diisi otomatis dari topik? Tidak: itu menyimpan duplikat yang terbaca seperti
 * sesuatu yang diketik orangnya. Controller-lah yang jatuh ke topik saat
 * subjeknya kosong, jadi datanya jujur dan layarnya tetap punya judul.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->string('subject')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->string('subject')->nullable(false)->change();
        });
    }
};
