<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lima tabel terakhir yang diminta situs publik tapi belum punya CMS.
 *
 * Kelimanya bentuk yang sama: daftar pendek, berurut, dan diganti seluruhnya
 * saat orang menyuntingnya. Yang membedakan cuma isinya.
 *
 * Sumber kontraknya `../../landing-page-nuxt/app/lib/api/types.ts` —
 * `BoardMember`, `SubCommittee`, `StandingCommittee`, `Partner`, dan
 * `HeritageMilestone`.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         * Executive Boards di `/about` (`112:3590`).
         *
         * `name` boleh memuat baris baru: kartunya merender nama dua baris kalau
         * ada `\n` di dalamnya, dan itu keputusan orang yang mengetiknya —
         * bukan sesuatu yang bisa ditebak dari panjang namanya.
         */
        Schema::create('board_members', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Jabatan, bukan gelar pekerjaan. Disimpan apa adanya dan
            // dikapitalkan CSS, jadi "president" dan "President" sama saja.
            $table->string('role');
            $table->string('portrait_path')->nullable();
            $table->string('portrait_alt')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'position']);
        });

        /*
         * Sub-Committees di `/about` (`114:3667`) — nama dan tujuan, dan itu
         * memang semuanya. Kartunya label dan panah.
         *
         * `href` nullable: halaman yang ditunjuknya belum ada, dan mengarang
         * URL sekarang berarti panah yang berujung 404.
         */
        Schema::create('sub_committees', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('href')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'position']);
        });

        /*
         * Standing Committees di `/governance` (`613:24909`).
         *
         * Berbeda dari `sub_committees`: yang ini membawa APA yang jadi
         * tanggung jawabnya. `remit` JSON karena desainnya menggambar tiga pil
         * terpisah — string yang digabung koma harus dipecah lagi untuk
         * menggambarnya, dan pemisahnya akan ikut tersimpan sebagai isi.
         */
        Schema::create('standing_committees', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('remit')->nullable();
            $table->string('icon_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'position']);
        });

        /*
         * Strip partner di beranda.
         *
         * `website_url` nullable, dan itu pertanyaan terbuka #6 di PRD situs
         * publik: alamat tujuan delapan logo belum diketahui, jadi marknya
         * sengaja bukan tautan sampai ada. Kolom kosong lebih jujur daripada
         * tautan karangan.
         */
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('logo_path');
            $table->string('website_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'position']);
        });

        /*
         * Timeline sejarah di `/about` (`88:1163`).
         *
         * `year` string: penanda di sumbu, bukan angka yang dihitung. "1974"
         * dan "1990s" sama-sama sah.
         */
        Schema::create('heritage_milestones', function (Blueprint $table) {
            $table->id();
            $table->string('year', 16);
            $table->string('title');
            $table->text('summary');
            $table->string('image_path')->nullable();
            $table->string('image_alt')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('heritage_milestones');
        Schema::dropIfExists('partners');
        Schema::dropIfExists('standing_committees');
        Schema::dropIfExists('sub_committees');
        Schema::dropIfExists('board_members');
    }
};
