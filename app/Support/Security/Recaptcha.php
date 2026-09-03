<?php

namespace App\Support\Security;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Verifikasi token reCAPTCHA v2 ke Google.
 *
 * Aktif hanya kalau kedua kunci terisi — tanpa itu seluruh mekanisme ini
 * transparan, jadi `.env` lokal yang belum diisi tidak menghalangi siapa pun
 * masuk dan seluruh tes tetap jalan tanpa memalsukan HTTP.
 */
final class Recaptcha
{
    public static function isEnabled(): bool
    {
        return filled(config('services.recaptcha.site_key'))
            && filled(config('services.recaptcha.secret_key'));
    }

    public static function siteKey(): ?string
    {
        return self::isEnabled() ? config('services.recaptcha.site_key') : null;
    }

    /**
     * Apakah token ini sah.
     *
     * ── Keputusan: GAGAL TERBUKA saat Google tidak bisa dihubungi. ──
     *
     * Kalau jaringan ke Google putus dan kita menolak semua login, seluruh
     * admin terkunci di luar — tepat pada saat mereka paling butuh masuk.
     * Yang menahan serangan tebak-sandi di sini bukan captcha melainkan
     * `RateLimiter` (5 percobaan/menit per email+IP, lihat `LoginRequest`),
     * dan itu tidak bergantung pada pihak ketiga mana pun.
     *
     * Jadi: token yang DITOLAK Google = gagal. Google yang tidak menjawab =
     * dilewatkan, dengan catatan di log supaya tidak lolos diam-diam. Kalau
     * kebijakan ini terlalu longgar untukmu, ubah `return true` di blok catch
     * jadi `return false` — hanya itu yang perlu disentuh.
     */
    public static function verify(?string $token, ?string $ip = null): bool
    {
        if (! self::isEnabled()) {
            return true;
        }

        if (blank($token)) {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout((int) config('services.recaptcha.timeout', 5))
                ->post(config('services.recaptcha.verify_url'), [
                    'secret' => config('services.recaptcha.secret_key'),
                    'response' => $token,
                    'remoteip' => $ip,
                ]);
        } catch (Throwable $e) {
            Log::warning('reCAPTCHA tidak bisa dihubungi, login dilewatkan.', [
                'exception' => $e->getMessage(),
            ]);

            return true;
        }

        if ($response->failed()) {
            Log::warning('reCAPTCHA membalas dengan galat, login dilewatkan.', [
                'status' => $response->status(),
            ]);

            return true;
        }

        $body = $response->json();

        if (($body['success'] ?? false) !== true) {
            // Kode galatnya dicatat karena beberapa di antaranya berarti SALAH
            // KONFIGURASI, bukan bot: `invalid-input-secret` (kunci rahasia
            // keliru) dan `invalid-keys`. Tanpa log ini, keduanya terlihat sama
            // dengan "captcha gagal" dan bisa berjam-jam tidak ketahuan.
            Log::info('reCAPTCHA menolak token.', [
                'errors' => $body['error-codes'] ?? [],
            ]);

            return false;
        }

        return true;
    }
}
