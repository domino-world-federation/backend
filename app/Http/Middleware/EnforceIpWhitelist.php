<?php

namespace App\Http\Middleware;

use App\Support\Security\IpWhitelist;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menegakkan daftar IP untuk akses backoffice — Figma `527:7038`.
 *
 * "Only active rules are enforced for Backoffice access" (`527:7870`) adalah
 * kalimat di layar; berkas ini yang membuatnya benar.
 *
 * ── Di mana ia dipasang, dan kenapa SESUDAH `auth`. ──
 *
 * Lingkup sebuah aturan bisa berupa peran atau orang tertentu, jadi keputusan
 * ini mustahil diambil sebelum diketahui siapa yang meminta. Konsekuensinya
 * disengaja: halaman login TIDAK dijaga daftar ini. Yang dijaga adalah sesi
 * yang sudah terbentuk — seseorang dari IP terlarang masih bisa membuka
 * `/login` dan mengetik sandi yang benar, lalu berhenti di 403. Rate limiter
 * 5×/menit yang menahan tebak-sandi ada di `LoginRequest` dan tidak bergantung
 * pada berkas ini.
 *
 * ── Kenapa `local` dilewati seluruhnya. ──
 *
 * Mesin pengembang berpindah jaringan, dan IP-nya berubah tanpa pemberitahuan.
 * Menegakkan daftar di sana berarti orang menambahkan `0.0.0.0/0` ke tabel
 * produksi supaya bisa bekerja — aturan yang lalu ikut terbawa saat deploy.
 * Lebih baik matikan di satu tempat yang jelas daripada mengundang jalan pintas
 * yang menyamar sebagai data.
 */
class EnforceIpWhitelist
{
    /** Satu nama log dengan kejadian otentikasi — keduanya soal siapa boleh masuk. */
    public const LOG_NAME = 'authentication';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || app()->environment('local')) {
            return $next($request);
        }

        $ip = $request->ip();

        if ($ip !== null && IpWhitelist::allows($user, $ip)) {
            return $next($request);
        }

        // Dicatat karena penolakan ini TIDAK meninggalkan jejak lain: ia bukan
        // login gagal, jadi `RecordAuthActivity` tidak melihatnya, dan orang
        // yang tertolak hanya melihat 403 tanpa keterangan. Tanpa baris ini,
        // "kenapa saya tidak bisa masuk" tidak punya satu pun jawaban di sisi
        // server.
        Log::warning('Akses backoffice ditolak oleh daftar IP.', [
            'user_id' => $user->getAuthIdentifier(),
            'ip' => $ip,
            'path' => $request->path(),
        ]);

        /*
         * Juga masuk JEJAK AUDIT, bukan cuma log berkas.
         *
         * Layar Audit Log punya kolom "Result" dengan nilai "Blocked"
         * (`528:11529`), dan penolakan ini salah satu dari tiga hal yang bisa
         * mengisinya. Berkas log tidak terlihat dari layar mana pun, jadi tanpa
         * baris ini "kenapa dia tidak bisa masuk" hanya bisa dijawab oleh orang
         * yang punya akses SSH.
         *
         * `access_denied` terdaftar di `ActivityLogController::BLOCKED_EVENTS`;
         * IP dan user agent-nya dicap `Activity::creating` di
         * `AppServiceProvider`, jadi tidak diisi di sini.
         */
        activity(self::LOG_NAME)
            ->causedBy($user)
            ->event('access_denied')
            ->withProperties(['path' => $request->path()])
            ->log('access_denied');

        abort(403, __('backoffice.ip_whitelist.denied'));
    }
}
