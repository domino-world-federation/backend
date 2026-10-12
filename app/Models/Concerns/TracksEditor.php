<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Mencatat siapa yang TERAKHIR menyimpan sebuah baris.
 *
 * Kolom "Last Modified" di tiap daftar menyebut nama, dan `author_id` tidak bisa
 * menjawabnya: yang menulis dan yang terakhir menyunting sering bukan orang yang
 * sama — dan justru perbedaan itu yang dicari saat sesuatu berubah tanpa ada
 * yang mengaku.
 *
 * Diisi lewat event `saving` model, BUKAN di tiap controller. Modul ini punya
 * enam controller dan tiap satunya punya jalur simpan lebih dari satu (form,
 * sakelar cepat, urutan). Menitipkannya ke pemanggil berarti suatu saat ada
 * jalur yang lupa — dan yang hilang justru catatan yang paling ingin ditelusuri.
 *
 * Tanpa pengguna yang login (seeder, perintah artisan, tes yang tidak
 * `actingAs`) kolomnya dibiarkan apa adanya. Menuliskan id palsu supaya kolomnya
 * terisi jauh lebih buruk daripada membiarkannya kosong.
 */
trait TracksEditor
{
    public static function bootTracksEditor(): void
    {
        static::saving(function (self $model): void {
            $id = Auth::id();

            // Yang sudah disetel pemanggil DI SIMPAN INI tidak ditimpa: impor
            // dan penyalinan data kadang memang perlu menyebut penyunting
            // selain dirinya. `isDirty`, BUKAN `=== null` — sampai 2026-10-12
            // pemeriksaannya "kolomnya kosong", dan baris yang sudah pernah
            // disimpan selalu berisi, jadi Last Modified membeku di nama
            // pembuatnya selamanya.
            if ($id !== null && ! $model->isDirty('updated_by_id')) {
                $model->setAttribute('updated_by_id', $id);
            }
        });
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }
}
