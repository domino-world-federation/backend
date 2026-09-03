<?php

namespace App\Support;

/**
 * Meringkas string user agent jadi "Chrome · macOS".
 *
 * Ditulis sendiri, bukan memakai pustaka pendeteksi perangkat: yang akurat
 * (`matomo/device-detector`) menyeret ribuan pola regex untuk membedakan ratusan
 * ponsel, sementara yang dipakai backoffice ini segelintir browser desktop.
 *
 * Konsekuensinya ringkasan ini KADANG SALAH untuk browser yang tidak dikenal —
 * dan karena itu string aslinya tetap disimpan utuh di log dan ditampilkan
 * sebagai tooltip. Ringkasannya untuk dibaca sekilas; yang mengikat tetap
 * aslinya.
 */
final class UserAgent
{
    /**
     * Urutannya PENTING dan tidak boleh diacak.
     *
     * Tiap browser menyamar sebagai pendahulunya demi kompatibilitas: Edge
     * memuat "Chrome" dan "Safari", Chrome memuat "Safari", Opera memuat
     * "Chrome". Yang paling spesifik harus diperiksa lebih dulu, kalau tidak
     * semuanya terbaca "Safari".
     */
    private const BROWSERS = [
        'Edge' => '/\bEdg(?:e|A|iOS)?\//i',
        'Opera' => '/\bOPR\/|\bOpera\b/i',
        'Samsung Internet' => '/\bSamsungBrowser\//i',
        'Chrome' => '/\bChrome\/|\bCriOS\//i',
        'Firefox' => '/\bFirefox\/|\bFxiOS\//i',
        'Safari' => '/\bSafari\//i',
        'curl' => '/^curl\//i',
        'Postman' => '/\bPostmanRuntime\//i',
    ];

    /** iOS diperiksa sebelum macOS: iPad melaporkan "Mac OS X" juga. */
    private const PLATFORMS = [
        'Android' => '/\bAndroid\b/i',
        'iOS' => '/\b(iPhone|iPad|iPod)\b/i',
        'Windows' => '/\bWindows NT\b/i',
        'macOS' => '/\bMac OS X\b|\bMacintosh\b/i',
        'Linux' => '/\bLinux\b|\bX11\b/i',
    ];

    /** @return array{browser: ?string, platform: ?string, label: ?string} */
    public static function summarise(?string $agent): array
    {
        if (blank($agent)) {
            return ['browser' => null, 'platform' => null, 'label' => null];
        }

        $browser = self::firstMatch(self::BROWSERS, $agent);
        $platform = self::firstMatch(self::PLATFORMS, $agent);

        $label = match (true) {
            $browser !== null && $platform !== null => "{$browser} · {$platform}",
            $browser !== null => $browser,
            $platform !== null => $platform,
            // Tidak dikenali: potong aslinya alih-alih menulis "Unknown".
            // "Unknown" menyembunyikan informasi yang sebenarnya ada.
            default => str($agent)->limit(40)->toString(),
        };

        return ['browser' => $browser, 'platform' => $platform, 'label' => $label];
    }

    /** @param array<string, string> $patterns */
    private static function firstMatch(array $patterns, string $agent): ?string
    {
        foreach ($patterns as $name => $pattern) {
            if (preg_match($pattern, $agent) === 1) {
                return $name;
            }
        }

        return null;
    }
}
