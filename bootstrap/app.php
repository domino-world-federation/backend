<?php

use App\Http\Middleware\EnforceIpWhitelist;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // API publik. Prefix `/api` dipasang di sini, `v1` di dalam berkasnya.
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            // SetLocale sebelum HandleInertiaRequests: yang kedua membaca
            // `App::getLocale()` untuk memilih kamus yang dikirim, jadi kalau
            // urutannya terbalik halaman pertama selalu memakai bahasa bawaan.
            SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Daftar IP ditegakkan untuk SEMUA rute web, bukan dipasang per grup.
        // Ia menyaring dirinya sendiri: tanpa pengguna yang login ia langsung
        // meneruskan, jadi halaman login dan `/logout` tidak terpengaruh —
        // sementara satu modul baru yang lupa mendaftar tidak bisa jadi lubang.
        // Lingkupnya bergantung peran, jadi ia HARUS berjalan sesudah sesi
        // terbaca; `append` menempatkannya di belakang seluruh stack `web`.
        $middleware->appendToGroup('web', EnforceIpWhitelist::class);

        $middleware->redirectGuestsTo('/login');

        /*
         * Mempercayai header `X-Forwarded-*` dari proxy di depan.
         *
         * Belum dibutuhkan selama nginx yang mengakhiri TLS di mesin yang sama
         * — di susunan itu skema datang lewat `fastcgi_param HTTPS`. Ia jadi
         * WAJIB begitu ada CDN atau load balancer di depan: tanpa ini Laravel
         * mengabaikan `X-Forwarded-Proto`, mengira semuanya http, dan tiap
         * `redirect()->route(...)` mengirim `Location: http://` yang diblokir
         * browser sebagai mixed content.
         *
         * `at: '*'` sah HANYA karena aplikasi ini tidak pernah terbuka
         * langsung ke internet — nginx satu-satunya yang bicara ke PHP-FPM,
         * lewat soket Unix. Kalau suatu saat FPM mendengarkan di TCP yang bisa
         * dijangkau siapa pun, daftar ini harus jadi alamat proxy yang
         * sebenarnya: header palsu dari luar akan dipercaya bulat-bulat.
         */
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        /*
         * Satu bentuk galat untuk seluruh API publik: `{ "message": "…" }`,
         * dan untuk 422 ditambah `errors` bawaan Laravel.
         *
         * Ada dua alasan, dan yang kedua yang membuatnya perlu:
         *
         *   1. Konsistensi. Pemakainya menulis satu penanganan galat, bukan
         *      satu per endpoint.
         *   2. Pesan bawaan Laravel MEMBOCORKAN isi perut aplikasi — dan ini
         *      terjadi bahkan dengan `APP_DEBUG=false`. Meminta berita yang
         *      tidak ada membalas "No query results for model
         *      [App\Models\NewsArticle]", yang memberi tahu dunia nama kelas,
         *      namespace, dan bahwa ini Laravel dengan route model binding.
         *      Bukan lubang, tapi tidak ada satu pun alasan mengirimkannya.
         *
         * Hanya untuk `api/*`. Backoffice memakai halaman galat Inertia-nya
         * sendiri, dan menyeragamkannya ke JSON akan mengubahnya jadi teks
         * mentah di layar orang.
         */
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $e->getStatusCode();

            /*
             * Pesan yang DIKARANG framework diganti; pesan yang kita tulis
             * sendiri dibiarkan.
             *
             * Yang di daftar ini lahir dari Laravel dan tidak pernah berguna
             * bagi pemakainya — 404 dari route model binding malah membocorkan
             * nama kelas. Sisanya (mis. 422 dari `abort_if` penyaring) memuat
             * kalimat yang justru dibutuhkan: "must be one of: home, members".
             * Menelannya jadi "Unprocessable Content" membuat orang menebak.
             */
            $generated = [
                404 => 'Not found.',
                405 => 'Method not allowed.',
                429 => 'Too many requests.',
                500 => 'Server error.',
            ];

            return response()->json([
                'message' => $generated[$status]
                    ?? ($e->getMessage() ?: (Response::$statusTexts[$status] ?? 'Error.')),
            ], $status, $e->getHeaders());
        });
    })->create();
