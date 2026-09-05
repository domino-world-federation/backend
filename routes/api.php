<?php

use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\SubmissionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API publik DWF
|--------------------------------------------------------------------------
|
| Dikonsumsi `../landing-page-nuxt` lewat satu gerbang tunggal,
| `app/lib/api/client.ts`. Path di bawah SAMA PERSIS dengan yang sudah ditulis
| fungsi-fungsi di berkas itu — kontraknya ditulis lebih dulu di sana, dan
| berkas ini menyusulinya.
|
| Read-only dan tanpa autentikasi (keputusan A4 di docs/PRD-API-PUBLIK.md):
| isinya memang publik. Yang perlu dijaga adalah endpoint TULIS, dan ketiganya
| belum ada di sini — lihat §7 di PRD.
|
| Prefix `/api` dipasang `bootstrap/app.php`, jadi path efektifnya
| `/api/v1/...`.
|
*/

Route::prefix('v1')->group(function () {
    // --- News ---
    Route::get('/news', [PublicController::class, 'news']);
    // Sebelum `{slug}`, yang menelan setiap ruas.
    Route::get('/news/categories', [PublicController::class, 'newsCategories']);
    Route::get('/news/{slug}', [PublicController::class, 'newsArticle']);

    // --- Documents ---
    Route::get('/resources', [PublicController::class, 'resources']);

    // --- Gallery ---
    Route::get('/gallery', [PublicController::class, 'gallery']);
    Route::get('/gallery/albums', [PublicController::class, 'galleryAlbums']);

    // --- FAQ, legal, settings ---
    Route::get('/faqs', [PublicController::class, 'faqs']);
    Route::get('/legal/{key}', [PublicController::class, 'legal']);
    // Naskah halaman depan yang tidak dimiliki modul mana pun — hero dan band
    // ajakan penutup. Sisanya datang dari endpoint modulnya sendiri.
    Route::get('/home', [PublicController::class, 'home']);
    Route::get('/settings', [PublicController::class, 'settings']);

    // Judul, deskripsi, dan gambar bagikan tiap halaman — satu response.
    Route::get('/seo', [PublicController::class, 'seo']);

    // --- Tournaments ---
    // Ketiga rute bernama di bawah SEBELUM `{slug}`, dengan alasan yang sama.
    Route::get('/tournaments/highlighted', [PublicController::class, 'highlightedTournament']);
    Route::get('/tournaments/featured', [PublicController::class, 'featuredEvent']);
    Route::get('/tournaments/showcase', [PublicController::class, 'showcaseEvents']);
    Route::get('/tournaments', [PublicController::class, 'tournaments']);
    Route::get('/tournaments/{slug}', [PublicController::class, 'tournament']);

    // --- Results & winners ---
    Route::get('/champions', [PublicController::class, 'champions']);
    Route::get('/olympic-results', [PublicController::class, 'olympicResults']);

    // --- Federations & people ---
    Route::get('/members', [PublicController::class, 'members']);
    Route::get('/stats', [PublicController::class, 'stats']);
    Route::get('/board-members', [PublicController::class, 'boardMembers']);
    Route::get('/sub-committees', [PublicController::class, 'subCommittees']);
    Route::get('/standing-committees', [PublicController::class, 'standingCommittees']);
    Route::get('/heritage-milestones', [PublicController::class, 'heritageMilestones']);
    Route::get('/partners', [PublicController::class, 'partners']);

    /*
     * --- Formulir (MENULIS) ---
     *
     * Di-throttle per alamat IP. Angkanya berbeda per formulir dan bukan
     * selera: langganan buletin ditekan sekali, laporan integritas mungkin
     * dikirim beberapa kali oleh orang yang sama dalam satu duduk (satu
     * insiden, beberapa kejadian), dan pesan kontak ada di antaranya.
     *
     * Yang ditahan throttle adalah banjir, bukan orangnya. Batas yang terlalu
     * ketat pada saluran integritas berarti laporan yang tidak jadi dikirim —
     * dan itu kegagalan yang jauh lebih mahal daripada beberapa baris sampah.
     *
     * ARGUMEN KETIGA — nama ember hitungannya — dan tanpa itu seluruh alinea di
     * atas tidak benar. `ThrottleRequests` menyusun kuncinya dari
     * `$prefix.sha1(domain|ip)`: TANPA prefix, keempat rute ini berbagi SATU
     * penghitung, dan angka yang berbeda cuma jadi ambang berbeda di atas
     * hitungan yang sama. Akibatnya nyata dan diuji pada 2026-09-05 — lima
     * pesan kontak membuat kotak buletin di footer menolak selama sisa menit
     * itu, dan sepuluh "notify me" mengunci saluran integritas. Persis yang
     * dijanjikan alinea di atas tidak akan terjadi.
     */
    Route::post('/contact', [SubmissionController::class, 'contact'])
        ->middleware('throttle:5,1,contact');

    Route::post('/newsletter', [SubmissionController::class, 'newsletter'])
        ->middleware('throttle:5,1,newsletter');

    Route::post('/tournaments/{tournament}/subscribe', [SubmissionController::class, 'subscribeToTournament'])
        ->whereNumber('tournament')
        ->middleware('throttle:10,1,tournament-subscribe');

    Route::post('/integrity-reports', [SubmissionController::class, 'integrityReport'])
        ->middleware('throttle:10,1,integrity');
});
