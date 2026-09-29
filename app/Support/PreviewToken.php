<?php

namespace App\Support;

/**
 * Token pratinjau editor halaman — membuka DRAF satu halaman lewat endpoint
 * publik, untuk iframe di layar editor.
 *
 * Tanpa tabel: `{halaman}.{kedaluwarsa}.{hmac}`, ditandatangani `app.key`.
 * Terikat ke satu halaman dan berumur pendek, karena ia berjalan di URL
 * situs publik (bisa tersalin, tercatat di log server) dan yang dibukanya
 * naskah yang belum diputuskan terbit. Layar editor membuat token baru setiap
 * kali dibuka.
 */
final class PreviewToken
{
    public const MINUTES = 120;

    public static function make(string $page): string
    {
        $payload = $page.'.'.now()->addMinutes(self::MINUTES)->getTimestamp();

        return $payload.'.'.self::sign($payload);
    }

    public static function valid(?string $token, string $page): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return false;
        }

        [$tokenPage, $expires, $signature] = $parts;

        return $tokenPage === $page
            && ctype_digit($expires)
            && (int) $expires >= now()->getTimestamp()
            && hash_equals(self::sign("{$tokenPage}.{$expires}"), $signature);
    }

    private static function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, (string) config('app.key'));
    }
}
