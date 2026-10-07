<?php

namespace App\Support;

use App\Models\SiteSetting;
use Closure;

/**
 * Naskah halaman publik yang disunting di editor halaman (`/pages`).
 *
 * Skemanya di `config('dwf.pages')`; nilainya di `site_settings`, satu baris
 * per field, kelompok `page.{halaman}`, kunci `{halaman}.{field}` — kunci
 * tabel itu primary key global, jadi nama halaman ikut di depannya.
 *
 * Dua keadaan per field: `value` yang tayang, `draft` yang sedang dikerjakan.
 * `draft` null = tidak ada perubahan tertunda. Nilai kosong (setelah terbit)
 * = situs memakai naskah bawaan di kodenya, dan endpoint publik tidak
 * menyebut fieldnya sama sekali.
 *
 * Jenis `lines` disimpan sebagai teks dengan satu baris per baris dan
 * dikirim ke situs sebagai array — judul yang dipecah baris di desain
 * dirender per baris, jadi pecahannya bagian dari naskah.
 *
 * Jenis `url` adalah tujuan tombol: path internal (`/contact`), jangkar
 * (`#`), atau URL penuh — aturan yang sama dengan layar Home Page lama,
 * karena tautan yang salah ketik gagal diam-diam: tombolnya tetap tergambar.
 */
final class PageContent
{
    public const GROUP_PREFIX = 'page.';

    /** @return array<string, array{label: string, path: string}> */
    public static function pages(): array
    {
        return collect(config('dwf.pages', []))
            ->map(fn (array $page) => ['label' => $page['label'], 'path' => $page['path']])
            ->all();
    }

    public static function exists(string $page): bool
    {
        return array_key_exists($page, config('dwf.pages', []));
    }

    public static function group(string $page): string
    {
        return self::GROUP_PREFIX.$page;
    }

    /**
     * Setiap field halaman, diratakan: `section.field` dan
     * `section.list.N.field` (N mulai 0).
     *
     * @return array<string, array{key: string, section: string, label: string, type: string, max: int, lines?: array{int, int}}>
     */
    public static function fields(string $page): array
    {
        $out = [];

        foreach (config("dwf.pages.{$page}.sections", []) as $sectionKey => $section) {
            foreach ($section['fields'] ?? [] as $fieldKey => $field) {
                $key = "{$sectionKey}.{$fieldKey}";
                $out[$key] = ['key' => $key, 'section' => $sectionKey] + $field;
            }

            foreach ($section['lists'] ?? [] as $listKey => $list) {
                for ($i = 0; $i < $list['count']; $i++) {
                    foreach ($list['fields'] as $fieldKey => $field) {
                        $key = "{$sectionKey}.{$listKey}.{$i}.{$fieldKey}";
                        $out[$key] = [
                            'key' => $key,
                            'section' => $sectionKey,
                            'label' => "{$list['label']} ".($i + 1)." — {$field['label']}",
                        ] + $field;
                    }
                }
            }
        }

        return $out;
    }

    /**
     * Section untuk layar editor: label, kunci field-nya berurutan, dan
     * tautan ke modul yang mengelola sisa section itu.
     *
     * @return list<array{key: string, label: string, fields: list<array<string, mixed>>, elsewhere: list<array{label: string, href: string}>}>
     */
    public static function sections(string $page): array
    {
        $fields = collect(self::fields($page));

        return collect(config("dwf.pages.{$page}.sections", []))
            ->map(fn (array $section, string $key) => [
                'key' => $key,
                'label' => $section['label'],
                'fields' => $fields->where('section', $key)->values()->all(),
                'elsewhere' => $section['elsewhere'] ?? [],
            ])
            ->values()
            ->all();
    }

    /** Nilai yang tayang, per kunci field (tanpa awalan halaman). @return array<string, string> */
    public static function published(string $page): array
    {
        return self::column($page, 'value');
    }

    /** Draf yang tertunda saja. @return array<string, string> */
    public static function drafts(string $page): array
    {
        return self::column($page, 'draft');
    }

    /**
     * Yang dikirim ke situs publik: hanya field yang terisi, `lines` sebagai
     * array. Pratinjau memakai draf bila ada, jatuh ke yang tayang bila tidak.
     *
     * @return array<string, string|list<string>>
     */
    public static function forSite(string $page, bool $preview = false): array
    {
        $fields = self::fields($page);
        $published = self::published($page);
        $drafts = $preview ? self::drafts($page) : [];

        $out = [];

        foreach ($fields as $key => $field) {
            $value = array_key_exists($key, $drafts) ? $drafts[$key] : ($published[$key] ?? null);

            if ($value === null || trim($value) === '') {
                continue;
            }

            $out[$key] = $field['type'] === 'lines' ? self::splitLines($value) : $value;
        }

        return $out;
    }

    /**
     * Batas pengaman per field. Batas di skema (`max`) adalah PANDUAN sejak
     * 2026-10-07 — layar editor memperingatkan dan minta konfirmasi, tapi tetap
     * menyimpan, karena pratinjaunya sudah memperlihatkan akibatnya. Yang ini
     * hanya menolak teks yang tidak wajar untuk satu field halaman.
     */
    public const HARD_MAX = 5000;

    /**
     * Aturan validasi untuk `values.*` — setiap field boleh kosong (= bawaan
     * kode). Panjang di skema hanya diperingatkan layar editor; yang ditolak
     * di sini: lewat `HARD_MAX`, jumlah baris di luar rentang `lines` (tata
     * letaknya bergantung padanya — bagan Our Global Network menggambar tepat
     * tiga kartu), dan tautan yang bukan path, jangkar, atau URL.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(string $page): array
    {
        $rules = ['values' => ['required', 'array']];

        foreach (self::fields($page) as $key => $field) {
            $rule = ['nullable', 'string'];

            $rule[] = 'max:'.self::HARD_MAX;

            if ($field['type'] === 'lines') {
                $rule[] = self::linesRule($field);
            }

            if ($field['type'] === 'url') {
                $rule[] = 'regex:#^(/[^\s]*|\#[^\s]*|https?://[^\s]+)$#';
            }

            // Titik di kunci field dibaca validator sebagai jalur bersarang;
            // kunci dikirim apa adanya sebagai satu tingkat, jadi titiknya
            // diloloskan.
            $rules['values.'.str_replace('.', '\.', $key)] = $rule;
        }

        return $rules;
    }

    /**
     * Menyimpan apa yang diketik sebagai draf. Field yang sama dengan yang
     * tayang tidak meninggalkan draf — "ada perubahan" di layar harus berarti
     * ada perubahan.
     *
     * @param  array<string, ?string>  $values
     * @return list<string> kunci yang kini punya draf
     */
    public static function saveDraft(string $page, array $values): array
    {
        $fields = self::fields($page);
        $published = self::published($page);
        $changed = [];

        foreach ($fields as $key => $field) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            $value = self::normalize($values[$key], $field['type']);
            $draft = $value === ($published[$key] ?? '') ? null : $value;

            $row = SiteSetting::query()->firstOrNew(['key' => self::storageKey($page, $key)]);

            if (! $row->exists && $draft === null) {
                continue;
            }

            $row->group = self::group($page);
            $row->draft = $draft;
            $row->save();

            if ($draft !== null) {
                $changed[] = $key;
            }
        }

        return $changed;
    }

    /**
     * Menerbitkan setiap draf: `value` ← `draft`, `draft` ← null.
     *
     * @return array<string, array{old: ?string, new: string}> yang berubah
     */
    public static function publish(string $page): array
    {
        $changes = [];

        SiteSetting::query()
            ->where('group', self::group($page))
            ->whereNotNull('draft')
            ->get()
            ->each(function (SiteSetting $row) use (&$changes, $page) {
                $key = substr($row->key, strlen($page) + 1);
                $changes[$key] = ['old' => $row->value, 'new' => $row->draft];

                $row->value = $row->draft;
                $row->draft = null;
                $row->save();
            });

        return $changes;
    }

    /** Membuang semua draf halaman. Mengembalikan jumlahnya. */
    public static function discard(string $page): int
    {
        return SiteSetting::query()
            ->where('group', self::group($page))
            ->whereNotNull('draft')
            ->update(['draft' => null]);
    }

    public static function storageKey(string $page, string $key): string
    {
        return "{$page}.{$key}";
    }

    /** @return list<string> */
    public static function splitLines(string $value): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split('/\R/', $value) ?: []),
            fn (string $line) => $line !== '',
        ));
    }

    private static function normalize(?string $value, string $type): string
    {
        $value = (string) $value;

        return $type === 'lines'
            ? implode("\n", self::splitLines($value))
            : trim($value);
    }

    /** @return array<string, string> */
    private static function column(string $page, string $column): array
    {
        $prefix = strlen($page) + 1;

        return SiteSetting::query()
            ->where('group', self::group($page))
            ->whereNotNull($column)
            ->pluck($column, 'key')
            ->mapWithKeys(fn (string $value, string $key) => [substr($key, $prefix) => $value])
            ->all();
    }

    /** @param  array{max: int, lines?: array{int, int}, label: string}  $field */
    private static function linesRule(array $field): Closure
    {
        [$min, $max] = $field['lines'] ?? [1, 1];

        return function (string $attribute, mixed $value, Closure $fail) use ($min, $max): void {
            $lines = self::splitLines((string) $value);

            // Kosong = bawaan kode; hanya naskah yang ditulis yang diperiksa.
            if ($lines === []) {
                return;
            }

            // Panjang tiap baris hanya diperingatkan layar editor (lihat
            // `HARD_MAX`); jumlah barisnya yang ditegakkan.
            if (count($lines) < $min || count($lines) > $max) {
                $fail(__('backoffice.pages.lines_count', ['min' => $min, 'max' => $max]));
            }
        };
    }
}
