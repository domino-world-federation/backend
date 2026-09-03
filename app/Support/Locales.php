<?php

namespace App\Support;

/**
 * Bahasa yang didukung backoffice.
 *
 * Daftar tertutup, bukan "apa pun yang ada foldernya di lang/": nilai locale
 * datang dari request, dan meneruskannya apa adanya ke `App::setLocale()`
 * berarti membiarkan orang menunjuk berkas terjemahan mana pun.
 */
final class Locales
{
    public const DEFAULT = 'en';

    /** @var array<string, array{label: string, short: string}> */
    public const SUPPORTED = [
        'id' => ['label' => 'Bahasa Indonesia', 'short' => 'ID'],
        'en' => ['label' => 'English', 'short' => 'EN'],
    ];

    /**
     * Apakah pengguna boleh mengganti bahasanya sendiri.
     *
     * Mati secara bawaan: pengalih bahasanya disembunyikan dari topbar dan
     * route `/locale` menolak. Seluruh mesin terjemahannya tetap utuh — dua
     * berkas kamus, middleware, dan tesnya — jadi menyalakannya kembali cukup
     * satu baris di `.env`.
     */
    public static function isSwitchable(): bool
    {
        return (bool) config('dwf.locale_switcher', false);
    }

    public static function isSupported(?string $locale): bool
    {
        return $locale !== null && array_key_exists($locale, self::SUPPORTED);
    }

    public static function sanitize(?string $locale): string
    {
        return self::isSupported($locale) ? $locale : self::DEFAULT;
    }

    /** @return array<int, array{value: string, label: string, short: string}> */
    public static function options(): array
    {
        return collect(self::SUPPORTED)
            ->map(fn (array $meta, string $value) => ['value' => $value, ...$meta])
            ->values()
            ->all();
    }
}
