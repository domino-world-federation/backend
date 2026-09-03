<?php

namespace App\Models;

use App\Models\Concerns\HasPosition;
use App\Models\Concerns\RecordsActivity;
use App\Models\Concerns\TracksEditor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['faq_category_id', 'question', 'answer', 'is_active', 'position', 'updated_by_id'])]
class Faq extends Model
{
    use HasFactory, HasPosition, RecordsActivity, TracksEditor;

    /** Halaman yang boleh dipilih di "Apply to Page" (`341:4861`). */
    public const PAGES = ['home', 'domino', 'tournament'];

    /** "A maximum of 3 questions per page." */
    public const MAX_PER_PAGE = 3;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FaqCategory::class, 'faq_category_id');
    }

    /**
     * Halaman tempat FAQ ini menempel, beserta peringkatnya di masing-masing.
     *
     * `position` di sini milik HALAMAN, bukan milik FAQ — itu perbedaan yang
     * membuat urutan di Home bisa berbeda dari urutan di Domino. `faqs.position`
     * yang lama tetap ada tapi artinya menyempit: urutan di halaman FAQ lengkap.
     */
    public function placements(): HasMany
    {
        return $this->hasMany(FaqPlacement::class);
    }

    /**
     * Daftar kunci halaman, untuk layar yang cuma perlu tahu "di mana saja".
     *
     * Diturunkan dari relasi, bukan kolom — kolom `pages` yang dulu ada sudah
     * dibuang justru karena ia jawaban KEDUA untuk pertanyaan yang sama.
     * Pemanggil yang memakainya di dalam perulangan wajib `with('placements')`.
     *
     * @return Attribute<array<int, string>, never>
     */
    protected function pages(): Attribute
    {
        return Attribute::get(fn (): array => $this->placements
            ->sortBy('position')
            ->pluck('page')
            ->all());
    }
}
