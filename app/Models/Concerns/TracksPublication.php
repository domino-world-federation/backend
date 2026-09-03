<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Mencatat SIAPA yang mengunggah dan SIAPA yang menayangkan.
 *
 * Daftar Gallery (`478:5884`) dan Documents (`369:5236`) menggambar tiga kolom
 * berbentuk sama — Published, Created, Last Modified — dan ketiganya memakai
 * sel yang sama: satu nama di atas, satu waktu di bawahnya. `TracksEditor`
 * menjawab yang ketiga; trait ini dua sisanya.
 *
 * ── Kenapa pengunggah dan penayang DIPISAH. ──
 *
 * Mereka sering bukan orang yang sama, dan itu justru alur yang dijanjikan
 * tombol "Save Draft": satu orang menyiapkan, orang lain menekan Publish.
 * Menyatukannya membuat kolom Published menyebut nama orang yang tidak pernah
 * memutuskan apa pun soal penayangannya.
 *
 * ── Kenapa di event model, bukan di controller. ──
 *
 * Alasan yang sama dengan `TracksEditor`: penayangan punya lebih dari satu
 * jalur — tombol Publish di formulir, pemilih Visibility di daftar, dan
 * penyuntingan yang mengubah jadwal. Menitipkannya ke masing-masing controller
 * berarti suatu saat ada jalur yang lupa, dan yang hilang justru catatan yang
 * paling ingin ditelusuri.
 *
 * Model yang memakainya WAJIB punya konstanta `STATUS_PUBLISHED` serta kolom
 * `created_by_id` dan `published_by_id`.
 */
trait TracksPublication
{
    public static function bootTracksPublication(): void
    {
        static::creating(function (self $model): void {
            $id = Auth::id();

            // Yang sudah disetel pemanggil tidak ditimpa — impor dan penyalinan
            // data kadang memang perlu menyebut pengunggah selain dirinya.
            if ($id !== null && $model->getAttribute('created_by_id') === null) {
                $model->setAttribute('created_by_id', $id);
            }
        });

        static::saving(function (self $model): void {
            // Dicatat SEKALI, saat ia pertama kali benar-benar tayang.
            // Menimpanya tiap kali status disentuh akan membuat kolom Published
            // menyebut orang yang cuma menyunting judulnya tiga bulan kemudian.
            if ($model->getAttribute('status') === static::STATUS_PUBLISHED
                && $model->getAttribute('published_by_id') === null
                && Auth::id() !== null) {
                $model->setAttribute('published_by_id', Auth::id());
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_id');
    }
}
