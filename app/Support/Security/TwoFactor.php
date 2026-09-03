<?php

namespace App\Support\Security;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PragmaRX\Google2FA\Google2FA;

/**
 * Otentikasi dua langkah berbasis TOTP (Google Authenticator).
 *
 * QR-nya digambar SENDIRI sebagai SVG, bukan diambil dari Google Chart API
 * seperti yang masih banyak beredar di internet: URL itu memuat rahasia TOTP
 * secara utuh di query string, jadi memakainya berarti mengirim kunci 2FA
 * setiap pengguna ke server pihak ketiga — dan mencatatnya di log akses,
 * riwayat browser, dan header Referer.
 */
final class TwoFactor
{
    /** Berapa banyak kode pemulihan dibuat saat pendaftaran. */
    public const RECOVERY_CODE_COUNT = 8;

    public static function isEnabled(): bool
    {
        return (bool) config('dwf.two_factor', true);
    }

    private static function engine(): Google2FA
    {
        return new Google2FA;
    }

    public static function generateSecret(): string
    {
        return self::engine()->generateSecretKey(32);
    }

    /**
     * Apakah kode ini sah untuk rahasia tersebut.
     *
     * Jendela toleransi 1 langkah (±30 detik) — jam ponsel yang meleset
     * beberapa detik adalah keluhan nomor satu pada TOTP, dan tanpa toleransi
     * ini sebagian pengguna tidak akan pernah bisa masuk. Lebih lebar dari itu
     * memperpanjang umur kode yang sudah dipakai orang lain.
     */
    public static function verify(?string $secret, ?string $code): bool
    {
        if (blank($secret) || blank($code)) {
            return false;
        }

        // Spasi dan strip dibuang: aplikasi authenticator menampilkan
        // "123 456", dan orang menyalinnya apa adanya.
        $code = preg_replace('/[\s-]/', '', $code) ?? '';

        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        return (bool) self::engine()->verifyKey($secret, $code, window: 1);
    }

    /** URI `otpauth://` yang dikodekan ke dalam QR. */
    public static function provisioningUri(string $secret, string $email): string
    {
        return self::engine()->getQRCodeUrl(
            config('app.name'),
            $email,
            $secret,
        );
    }

    /** SVG siap tempel — tanpa request ke mana pun. */
    public static function qrSvg(string $secret, string $email): string
    {
        $writer = new Writer(new ImageRenderer(
            new RendererStyle(256, margin: 0),
            new SvgImageBackEnd,
        ));

        return $writer->writeString(self::provisioningUri($secret, $email));
    }

    /** @return array<int, string> */
    public static function generateRecoveryCodes(): array
    {
        return collect(range(1, self::RECOVERY_CODE_COUNT))
            ->map(fn () => sprintf('%s-%s', bin2hex(random_bytes(5)), bin2hex(random_bytes(5))))
            ->all();
    }
}
