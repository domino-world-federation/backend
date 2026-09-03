<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Dasar seluruh resource API publik.
 *
 * Ia menegakkan dua dari enam aturan lintas endpoint di
 * `../../../docs/PRD-API-PUBLIK.md` §5 — dua yang paling mudah dilanggar tanpa
 * menghasilkan galat apa pun:
 *
 * **§5.3 — `id` selalu string.** 29 tipe di `types.ts` menulis `id: string`.
 * PostgreSQL memberi bigint dan Laravel meng-serialize-nya sebagai number JSON;
 * selisihnya tidak pernah muncul sebagai error, hanya sebagai perbandingan
 * `===` yang diam-diam gagal di frontend.
 *
 * **§5.4 — field opsional DIHILANGKAN, bukan dikirim `null`.** Kontraknya
 * `field?: string`, bukan `field: string | null`. Komponen menulis
 * `v-if="item.field"` dan sejumlah guard membedakan `undefined` dari `null`.
 *
 * Subclass menulis `payload()`, bukan `toArray()`: yang kedua sudah dipakai di
 * sini untuk menyaring hasilnya.
 */
abstract class PublicResource extends JsonResource
{
    /**
     * Isi resource, sebelum penyaringan.
     *
     * @return array<string, mixed>
     */
    abstract protected function payload(Request $request): array;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return array_filter(
            $this->payload($request),
            // `null` dibuang; `false`, `0`, dan `''` TIDAK. `array_filter`
            // tanpa callback akan membuang ketiganya juga — dan `0` adalah
            // jawaban yang sah untuk "berapa peserta".
            static fn ($value) => $value !== null,
        );
    }

    /**
     * Membungkus koleksi TANPA kunci `data`.
     *
     * `client.ts` membaca array telanjang (`request<NewsArticle[]>`), dan
     * pembungkus bawaan Laravel akan membuat `response.map is not a function`
     * di setiap halaman sekaligus.
     */
    public static function bare(iterable $items): array
    {
        return static::collection(collect($items))->resolve();
    }

    /** `id` sebagai string — dipakai tiap subclass. */
    protected function idString(): string
    {
        return (string) $this->resource->getKey();
    }
}
