<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Slug unik yang diturunkan dari sebuah kolom.
 *
 * Keunikannya dijaga dengan menambah akhiran angka, bukan dengan melempar
 * galat: dua berita boleh berjudul sama, dan editor tidak seharusnya diminta
 * mengarang judul lain hanya supaya URL-nya muat.
 */
trait HasSlug
{
    public static function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: 'item';
        $slug = $base;
        $suffix = 2;

        while (
            static::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
