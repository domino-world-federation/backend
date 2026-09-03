<?php

use App\Http\Middleware\EnforceIpWhitelist;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

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
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
