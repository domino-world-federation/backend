<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Satu FAQ yang menempel di satu halaman, pada satu peringkat.
 *
 * Ini yang membuat urutan bisa berbeda per halaman. Sebelumnya penempatan
 * disimpan sebagai daftar kunci di kolom JSON `faqs.pages` dan peringkatnya
 * ikut `faqs.position` yang global — bentuk yang tidak punya tempat untuk
 * menyimpan dua peringkat berbeda untuk satu pertanyaan.
 *
 * TIDAK memakai `RecordsActivity`: satu kali Simpan di layar "FAQ per Halaman"
 * menulis ulang seluruh peringkat di halaman itu, dan mencatatnya baris per
 * baris akan menenggelamkan jejak audit dengan sembilan entri untuk satu
 * tindakan. `FaqController` mencatatnya sebagai satu entri.
 */
#[Fillable(['faq_id', 'page', 'position'])]
class FaqPlacement extends Model
{
    public function faq(): BelongsTo
    {
        return $this->belongsTo(Faq::class);
    }

    /** Menaruh penempatan baru di ujung halaman ITU, bukan di ujung tabel. */
    public static function nextPositionOn(string $page): int
    {
        return (int) static::query()->where('page', $page)->max('position') + 1;
    }

    /**
     * Menulis ulang peringkat satu halaman.
     *
     * Dibatasi ke satu halaman dengan sengaja: `applyOrder()` global milik
     * `HasPosition` akan menomori ulang seluruh tabel, dan itu persis kesalahan
     * yang membuat urutan Home ikut bergeser saat Domino diurutkan.
     *
     * @param  array<int, int|string>  $orderedFaqIds
     */
    public static function applyOrderOn(string $page, array $orderedFaqIds): void
    {
        DB::transaction(function () use ($page, $orderedFaqIds) {
            foreach (array_values($orderedFaqIds) as $index => $faqId) {
                static::query()
                    ->where('page', $page)
                    ->where('faq_id', $faqId)
                    ->update(['position' => $index + 1]);
            }
        });
    }
}
