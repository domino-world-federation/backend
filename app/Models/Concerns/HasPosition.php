<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Baris yang urutannya diatur tangan lewat layar "Manage Order".
 *
 * `position` ditulis sekali per baris di dalam satu transaksi. Menyimpan urutan
 * baris demi baris tanpa transaksi meninggalkan daftar setengah tersusun kalau
 * request putus di tengah — dan urutan setengah jadi lebih membingungkan
 * daripada urutan lama.
 */
trait HasPosition
{
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('id');
    }

    /** Menaruh baris baru di ujung, bukan di awal. */
    public static function nextPosition(): int
    {
        return (int) static::query()->max('position') + 1;
    }

    /** @param  array<int, int|string>  $orderedIds */
    public static function applyOrder(array $orderedIds): void
    {
        DB::transaction(function () use ($orderedIds) {
            foreach (array_values($orderedIds) as $index => $id) {
                static::query()->whereKey($id)->update(['position' => $index + 1]);
            }
        });
    }
}
