<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Naskah beranda pindah dari layar Home Page (kelompok `home`, kunci
 * `hero_*` / `closing_*`) ke Editor Halaman (kelompok `page.home`, kunci
 * `home.hero.*` / `home.closing.*`).
 *
 * DISALIN, bukan dipindah: yang sudah diisi tim tetap tayang tanpa ada yang
 * mengetik ulang, sebagai nilai TERBIT (bukan draf) karena memang itulah yang
 * sedang tayang. Baris lama dibiarkan — `/api/v1/home` membacanya dari kunci
 * baru sekarang, tapi baris lama tidak merugikan siapa pun dan menghapusnya
 * menutup jalan kembali.
 *
 * Kunci baru yang SUDAH ada tidak ditimpa: migrasi ini aman dijalankan di
 * database yang Editor Halamannya sudah dipakai.
 */
return new class extends Migration
{
    private const MAP = [
        'hero_tagline' => 'home.hero.tagline',
        'hero_headline' => 'home.hero.headline',
        'hero_mission' => 'home.hero.mission',
        'hero_accountability' => 'home.hero.accountability',
        'hero_primary_cta' => 'home.hero.primary_cta',
        'hero_primary_cta_url' => 'home.hero.primary_cta_url',
        'hero_secondary_cta' => 'home.hero.secondary_cta',
        'hero_secondary_cta_url' => 'home.hero.secondary_cta_url',
        'closing_headline' => 'home.closing.headline',
        'closing_body' => 'home.closing.body',
        'closing_cta' => 'home.closing.cta',
        'closing_cta_url' => 'home.closing.cta_url',
    ];

    public function up(): void
    {
        $old = DB::table('site_settings')
            ->where('group', 'home')
            ->whereIn('key', array_keys(self::MAP))
            ->pluck('value', 'key');

        foreach (self::MAP as $from => $to) {
            $value = $old[$from] ?? null;

            if ($value === null || trim($value) === '') {
                continue;
            }

            if (DB::table('site_settings')->where('key', $to)->exists()) {
                continue;
            }

            DB::table('site_settings')->insert([
                'key' => $to,
                'group' => 'page.home',
                'value' => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Baris lama tidak pernah disentuh, jadi mundur cukup membuang kunci
        // baru. Yang sedang punya draf dibiarkan — itu pekerjaan seseorang
        // yang belum diputuskan, bukan salinan.
        DB::table('site_settings')
            ->whereIn('key', array_values(self::MAP))
            ->whereNull('draft')
            ->delete();
    }
};
