<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Galeri mendapat waktu tayang — layar Add Gallery (`478:6930`) meminta
 * "Publish Time: Now / Schedule", dan tombolnya Save Draft · Cancel · Publish.
 *
 * `is_active` DIBUANG, bukan disimpan berdampingan. Dua sakelar yang sama-sama
 * menjawab "apakah ini terlihat" adalah cara paling pasti untuk sampai pada
 * baris yang aktif tapi masih draft — dan tidak ada seorang pun yang bisa
 * menjelaskan apa artinya. Bentuknya sekarang persis sama dengan News: satu
 * kolom `status`, satu `published_at`.
 *
 * Datanya dipindahkan, tidak dibuang: yang aktif jadi `published` dengan
 * tanggal tayang = tanggal dibuat, sisanya jadi `draft`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gallery_items', function (Blueprint $table) {
            // String, bukan enum Postgres — menambah status baru nanti tidak
            // perlu ALTER TYPE. Sama seperti `news_articles.status`.
            $table->string('status', 16)->default('draft')->after('alt');
            $table->timestamp('published_at')->nullable()->after('status');
        });

        DB::table('gallery_items')->where('is_active', true)->update([
            'status' => 'published',
            'published_at' => DB::raw('created_at'),
        ]);

        Schema::table('gallery_items', function (Blueprint $table) {
            $table->dropColumn('is_active');
            $table->index(['status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::table('gallery_items', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('alt');
        });

        DB::table('gallery_items')->whereIn('status', ['published', 'scheduled'])->update(['is_active' => true]);
        DB::table('gallery_items')->whereIn('status', ['draft', 'unpublished'])->update(['is_active' => false]);

        Schema::table('gallery_items', function (Blueprint $table) {
            $table->dropIndex(['status', 'published_at']);
            $table->dropColumn(['status', 'published_at']);
        });
    }
};
