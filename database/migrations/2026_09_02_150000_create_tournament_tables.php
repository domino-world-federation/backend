<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Turnamen — formulir `585:11241`, sepuluh section.
 *
 * Empat tabel, bukan sepuluh. Delapan dari sepuluh section desain adalah
 * kelompok field yang menempel pada SATU turnamen (venue, hadiah, kontak,
 * format, kelayakan); hanya dua yang benar-benar berulang — ofisial dan jadwal
 * — dan satu yang menunjuk baris yang sudah ada di tabel lain (dokumen).
 * Memecah tiap section jadi tabelnya sendiri berarti delapan join untuk
 * membuka satu formulir.
 *
 * ── Yang TIDAK ada di sini, dan itu keputusan desain. ──
 *
 * "Results & Winners is not part of Create Tournament. It is managed from a
 * separate menu after the tournament is completed" (`596:11483`). Jadi tabel
 * pemenang tidak dibuat sekarang; ia menunggu layarnya sendiri.
 *
 * ── Status turnamen DITURUNKAN, bukan disimpan. ──
 *
 * Frontend meminta `status` (upcoming|live|completed) dan `registration`
 * (open|upcoming|ongoing|closed) — lihat `landing-page-nuxt/app/lib/api/types.ts`.
 * Keduanya sepenuhnya ditentukan tanggal yang sudah ada di tabel ini, jadi
 * menyimpannya berarti kolom yang basi setiap tengah malam kecuali ada cron
 * yang menyegarkannya. Diturunkan di model, sama seperti `visibility`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();

            // --- Basic Information (`596:11005`) ---
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('coverage', 64);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('city');
            $table->string('country');
            $table->string('rules_format', 64);
            $table->string('hero_image_path')->nullable();
            $table->text('overview');

            // --- Venue (`596:11111`) ---
            $table->string('venue_name');
            $table->string('venue_address');
            // "Latitude, Longitude or map pin" — presisi 7 desimal cukup untuk
            // ~1 cm, jauh melebihi yang dibutuhkan pin sebuah gedung.
            $table->decimal('venue_lat', 10, 7);
            $table->decimal('venue_lng', 10, 7);

            // --- Prize Information (`596:11158`), seluruhnya opsional ---
            // `decimal`, bukan float: hadiah adalah uang, dan float membuat
            // 50000 tersimpan sebagai 49999.999999.
            $table->decimal('prize_amount', 12, 2)->nullable();
            $table->string('prize_currency', 8)->nullable();
            $table->string('prize_description', 240)->nullable();
            $table->string('prize_image_path')->nullable();

            // --- Tournament Contact (`596:11206`), opsional ---
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 40)->nullable();

            // --- Eligibility & Registration (`596:11304`) ---
            $table->date('registration_starts_on')->nullable();
            $table->date('registration_ends_on')->nullable();
            $table->string('dwf_id_requirement')->nullable();
            $table->string('eligibility');
            $table->string('registration_method');

            // --- Tournament Format (`596:11409`) ---
            $table->string('game_format', 80);
            $table->unsignedInteger('participant_count')->nullable();
            $table->string('participant_type', 16);
            $table->text('competition_system');
            $table->text('scoring');

            // Kosakata penayangan yang sama dengan News, Gallery, dan Documents.
            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();

            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'starts_on']);
        });

        // --- Officials & Referees (`596:11241`) — kelompok berulang ---
        Schema::create('tournament_officials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->string('photo_path')->nullable();
            $table->string('name');
            $table->string('role');
            $table->string('country');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['tournament_id', 'position']);
        });

        // --- Schedule (`596:11361`) — "add, remove, and reorder" ---
        Schema::create('tournament_schedule_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->date('held_on');
            $table->time('starts_at');
            $table->string('activity', 120);
            // "Madrid Arena • Main Hall" — satu baris, dipisah komponen kalau
            // perlu. Disimpan seperti yang diketik orangnya.
            $table->string('area', 120)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['tournament_id', 'position']);
        });

        /*
         * --- Regulations & Rules (`596:11467`) ---
         *
         * "files are not re-uploaded here" — dokumennya MENUNJUK baris
         * `documents` yang sudah ada, jadi tanggal terbit, jenis, dan ukuran
         * berkasnya tetap satu sumber. Pivot, bukan kolom: satu dokumen bisa
         * dipakai beberapa turnamen sekaligus.
         */
        Schema::create('document_tournament', function (Blueprint $table) {
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);

            $table->primary(['tournament_id', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_tournament');
        Schema::dropIfExists('tournament_schedule_entries');
        Schema::dropIfExists('tournament_officials');
        Schema::dropIfExists('tournaments');
    }
};
