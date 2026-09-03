<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Results & Winners — menu terpisah yang dijanjikan desain Add Tournament.
 *
 * "Results & Winners is not part of Create Tournament. It is managed from a
 * separate menu after the tournament is completed" (`596:11483`). Tiga tabel,
 * karena tiga hal berbeda dimintanya oleh situs publik: pemenang SATU turnamen,
 * daftar juara lintas tahun (Champions Hall), dan tabel hasil Olympic.
 *
 * Bentuknya mengikuti `TournamentWinner`, `Champion`, dan `OlympicResult` di
 * `../../landing-page-nuxt/app/lib/api/types.ts`.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         * Pemenang satu turnamen — blok "Results & Winners" di halaman
         * turnamen (`517:2180`).
         *
         * `names` SATU string, bukan relasi ke pemain: gelar ganda dimenangkan
         * dua orang dan desainnya mencetaknya sebagai satu baris ("Luis Ortega
         * & Mateo Ruiz"). Memecahnya jadi tabel pemain berarti membangun
         * register pemain yang tidak diminta siapa pun, lalu merangkainya
         * kembali jadi kalimat yang sama.
         *
         * `portrait_paths` JSON karena kartunya menggambar SATU lingkaran per
         * pemenang — jumlahnya mengikuti berapa orang yang menang, bukan tetap.
         */
        Schema::create('tournament_winners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            // "CHAMPION", "RUNNER-UP", "THIRD PLACE" — pita di kepala kartunya.
            $table->string('rank_label', 40);
            $table->string('names');
            $table->string('country', 120);
            $table->json('portrait_paths')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['tournament_id', 'position']);
        });

        /*
         * Champions Hall (`381:17645`) — daftar juara lintas tahun, TIDAK
         * terikat ke satu turnamen.
         *
         * Sengaja bukan turunan `tournament_winners`: rel juara di beranda
         * memuat gelar yang turnamennya belum tentu pernah masuk CMS ini, dan
         * memaksanya punya baris turnamen berarti mengarang turnamen demi satu
         * kartu.
         *
         * `portrait_path` NULLABLE, dan itu bukan kelalaian — lihat risiko R16
         * di `../../landing-page-nuxt/docs/PRD.md`: desain mengisi kartunya
         * dengan foto tokoh publik nyata dan menamai mereka juara federasi ini.
         * Kartu tanpa potret jatuh ke panel gradien, jadi bloknya lengkap tanpa
         * menyatakan bahwa seseorang memenangkan gelar yang tidak pernah ada.
         */
        Schema::create('champions', function (Blueprint $table) {
            $table->id();
            // Baris kecil di atas nama: "2024 World Championship".
            $table->string('event');
            $table->string('name');
            $table->string('portrait_path')->nullable();
            $table->string('portrait_alt')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'position']);
        });

        /*
         * Tabel hasil Olympic.
         *
         * `year` STRING, bukan integer: kontraknya menyebutnya label, dan
         * halaman itu tidak pernah menghitung apa pun dengannya. Integer akan
         * menolak "2024–25" yang wajar untuk ajang lintas tahun.
         */
        Schema::create('olympic_results', function (Blueprint $table) {
            $table->id();
            $table->string('year', 16);
            $table->string('event');
            $table->string('category');
            $table->string('winners');
            // Kolom kanan — negara ATAU federasi nasional.
            $table->string('federation');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('olympic_results');
        Schema::dropIfExists('champions');
        Schema::dropIfExists('tournament_winners');
    }
};
