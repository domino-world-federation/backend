<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'type', 'held_on', 'tournament_id'])]
class GalleryEvent extends Model
{
    use HasFactory, HasSlug, RecordsActivity;

    public const TYPES = ['event', 'tournament'];

    protected function casts(): array
    {
        return ['held_on' => 'date'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(GalleryItem::class);
    }

    /**
     * Album turnamen menunjuk turnamennya; album acara biasa tidak punya ini.
     *
     * Nullable, dan bukan karena longgar: baris yang dibuat sebelum tautan ini
     * ada memang tidak punya jawabannya. Lihat migrasinya.
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * Album milik sebuah turnamen — dibuat saat pertama kali dibutuhkan.
     *
     * Nama dan tanggalnya DISALIN ULANG tiap kali, bukan ditulis sekali:
     * turnamennya yang jadi sumber, jadi mengganti namanya di modul Tournaments
     * ikut mengganti judul albumnya. Yang TIDAK ikut adalah slug — ia alamat
     * publik album itu, dan alamat yang bergeser tiap judul disunting adalah
     * tautan yang mati di tempat orang menyimpannya.
     *
     * Tinggal di model, bukan di controller, karena penyemai memerlukannya juga
     * — dan dua salinan aturan "album turnamen itu seperti apa" adalah dua
     * bentuk data yang lambat laun berbeda.
     */
    public static function forTournament(Tournament $tournament): self
    {
        $album = static::query()->firstOrNew(['tournament_id' => $tournament->id]);

        $album->type = 'tournament';
        $album->name = $tournament->name;
        $album->held_on = $tournament->starts_on;
        $album->slug ??= static::uniqueSlug($tournament->name);
        $album->save();

        return $album;
    }
}
