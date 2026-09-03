<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Documents naik dari sakelar dua keadaan ke Visibility empat keadaan.
 *
 * Daftar `369:5236` menggambar kolom **Visibility** dengan pemilih di dalam sel
 * — ikon globe, teks, chevron (`478:5342`) — persis seperti News dan Gallery,
 * dan nilainya "Published", "Edit Draft", "Unpublished". Sakelar `is_active`
 * yang ada sekarang hanya bisa menjawab dua dari empat.
 *
 * Formulirnya juga ikut: `262:3449` mengganti sakelar Status dengan
 * "Publish Time: Now / Schedule" — kolom `published_at` yang dituntutnya belum
 * pernah ada di tabel ini.
 *
 * `is_active` DIPETAKAN, bukan dibuang begitu saja: `true` jadi `published`,
 * `false` jadi `unpublished`. Dokumen yang sudah tayang tidak boleh diam-diam
 * turun jadi draft hanya karena kolomnya berganti bentuk.
 *
 * `published_at` untuk baris lama diisi `created_at`, bukan `now()`: yang
 * pertama tanggal yang paling mendekati kebenaran, yang kedua membuat seluruh
 * arsip terlihat terbit hari ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('press_releases', function (Blueprint $table) {
            $table->string('status', 16)->default('published')->after('category');
            $table->timestamp('published_at')->nullable()->after('status');
            $table->foreignId('created_by_id')->nullable()->after('updated_by_id')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('published_by_id')->nullable()->after('created_by_id')
                ->constrained('users')->nullOnDelete();
        });

        DB::table('press_releases')->update([
            'status' => DB::raw("case when is_active then 'published' else 'unpublished' end"),
            'published_at' => DB::raw('case when is_active then created_at else null end'),
        ]);

        Schema::table('press_releases', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('press_releases', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('category');
        });

        DB::table('press_releases')->update([
            'is_active' => DB::raw("status = 'published'"),
        ]);

        Schema::table('press_releases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('published_by_id');
            $table->dropConstrainedForeignId('created_by_id');
            $table->dropColumn(['status', 'published_at']);
        });
    }
};
