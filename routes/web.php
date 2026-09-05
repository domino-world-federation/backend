<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Cms\BlockController;
use App\Http\Controllers\Cms\ContactMessageController;
use App\Http\Controllers\Cms\ContactSettingController;
use App\Http\Controllers\Cms\DocumentController;
use App\Http\Controllers\Cms\FaqCategoryController;
use App\Http\Controllers\Cms\FaqController;
use App\Http\Controllers\Cms\FederationStatController;
use App\Http\Controllers\Cms\GalleryController;
use App\Http\Controllers\Cms\HomePageController;
use App\Http\Controllers\Cms\IntegrityReportController;
use App\Http\Controllers\Cms\IpWhitelistRuleController;
use App\Http\Controllers\Cms\LegalPageController;
use App\Http\Controllers\Cms\MemberFederationController;
use App\Http\Controllers\Cms\NewsArticleController;
use App\Http\Controllers\Cms\NewsCategoryController;
use App\Http\Controllers\Cms\NewsletterController;
use App\Http\Controllers\Cms\NotificationController;
use App\Http\Controllers\Cms\PeopleController;
use App\Http\Controllers\Cms\ResultController;
use App\Http\Controllers\Cms\RoleController;
use App\Http\Controllers\Cms\SeoController;
use App\Http\Controllers\Cms\TournamentController;
use App\Http\Controllers\Cms\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EditorImageController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MediaController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Di luar grup `auth` dan `guest`: halaman login juga diterjemahkan, jadi
// tamu harus bisa mengganti bahasa sebelum punya akun untuk menyimpannya.
Route::put('/locale', LocaleController::class)->name('locale.update');

// 2FA duduk DI ANTARA `guest` dan `auth`: penggunanya sudah membuktikan kata
// sandi tapi belum punya sesi login, jadi `guest` akan menolaknya setelah kode
// benar dan `auth` akan menolaknya sebelum itu. Penjagaannya ada di controller,
// yang memeriksa sesi tertunda dan mengalihkan ke /login kalau tidak ada.
Route::prefix('two-factor')->name('two-factor.')->group(function () {
    Route::get('/setup', [TwoFactorController::class, 'setup'])->name('setup');
    Route::post('/setup', [TwoFactorController::class, 'confirm'])->name('confirm');
    Route::get('/challenge', [TwoFactorController::class, 'challenge'])->name('challenge');
    Route::post('/challenge', [TwoFactorController::class, 'verify'])->name('verify');
    Route::get('/recovery-codes', [TwoFactorController::class, 'recovery'])->name('recovery');
});

// Menerima undangan admin (`529:9714`). DI LUAR `auth` DAN `guest`: orangnya
// belum punya sandi untuk login, jadi `auth` menolaknya — sementara `guest`
// akan menolak super admin yang sedang menguji tautannya sendiri, dan
// penolakan itu terbaca sebagai "tautannya rusak".
Route::get('/invitation/{token}', [InvitationController::class, 'show'])->name('invitation.show');
Route::post('/invitation/{token}', [InvitationController::class, 'accept'])->name('invitation.accept');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

// DI LUAR grup `auth`: pengguna yang berhenti di layar 2FA sudah membuktikan
// kata sandinya tapi belum login, jadi `auth` akan menolaknya — dan penanda
// "menunggu 2FA" di sesinya tidak akan pernah terhapus. Logout saat belum
// login memang tidak melakukan apa-apa; itu bukan alasan untuk menolaknya.
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

/*
 * Berkas dokumen — DI LUAR grup `auth`, dan itu memang tujuannya.
 *
 * Situs publik menautkannya untuk pengunjung yang tidak login. Yang menahan
 * berkas yang belum tayang adalah `MediaController`, yang memeriksa keadaan
 * dokumennya pada TIAP permintaan — bukan `auth`, dan bukan nama berkas acak.
 *
 * `whereNumber` karena rutenya memakai id, bukan slug: slug berubah saat judul
 * disunting, dan tautan yang sudah beredar di siaran pers tidak ikut berubah.
 */
Route::get('/media/documents/{document}', [MediaController::class, 'document'])
    ->whereNumber('document')
    ->name('media.document');

Route::middleware('auth')->group(function () {
    Route::redirect('/', '/dashboard');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    /*
     * Lonceng di topbar.
     *
     * Tanpa `can:` apa pun, dan itu bukan kelalaian: notifikasi milik satu
     * orang, dan kedua aksi ini bekerja lewat relasi milik pengguna yang
     * sedang masuk. Izin modul menentukan siapa yang MENERIMA notifikasinya
     * (lihat `SubmissionRecipients`), bukan siapa yang boleh membaca
     * loncengnya sendiri.
     */
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])
        ->name('notifications.read-all');
    Route::get('/notifications/{notification}', [NotificationController::class, 'open'])
        ->name('notifications.open');

    // Unggahan gambar dari dalam editor teks kaya — dipakai News, FAQ, dan
    // Legal Pages, jadi ia berdiri sendiri dan tidak menempel di salah satunya.
    Route::post('/editor/images', [EditorImageController::class, 'store'])
        ->middleware('can:upload-editor-image')
        ->name('editor.images.store');

    // Referensi komponen. Statis — tidak perlu controller sendiri.
    Route::get('/design-system', fn () => Inertia::render('DesignSystem/Index'))
        ->name('design-system');

    // ---------------------------------------------------------------- News
    // `categories` didaftarkan SEBELUM `news/{article}`; kalau terbalik,
    // `/news/categories` cocok dengan `{article}` dan berakhir 404 karena
    // "categories" bukan id.
    // Satu layar: daftar, tambah, ubah, dan hapus semuanya di tempat.
    // Tidak ada `create` atau `edit` — ketiganya dulu melakukan hal yang sama.
    Route::prefix('news/categories')->name('news.categories.')->middleware('can:news.view')->group(function () {
        Route::get('/', [NewsCategoryController::class, 'index'])->name('index');
        Route::middleware('can:news.create')->group(function () {
            Route::post('/', [NewsCategoryController::class, 'store'])->name('store');
        });

        // `whereNumber` per route, bukan di grup: `where` setelah `group()`
        // tidak berlaku surut ke route yang sudah terdaftar di dalamnya.
        // Tanpa batasan ini, `/news/categories/create` cocok dengan
        // `{category}` dan membalas 405, bukan 404.
        Route::middleware('can:news.update')->group(function () {
            Route::put('/{category}', [NewsCategoryController::class, 'update'])
                ->whereNumber('category')->name('update');
        });

        Route::middleware('can:news.delete')->group(function () {
            Route::delete('/{category}', [NewsCategoryController::class, 'destroy'])
                ->whereNumber('category')->name('destroy');
        });
    });

    Route::prefix('news')->name('news.articles.')->middleware('can:news.view')->group(function () {
        Route::get('/', [NewsArticleController::class, 'index'])->name('index');
        Route::get('/create', [NewsArticleController::class, 'create'])->name('create');
        // Sebelum `/{article}`. `whereNumber` di bawah sudah menjaganya, tapi
        // urutannya tetap ditulis benar supaya tidak bergantung pada itu saja.
        Route::get('/export', [NewsArticleController::class, 'export'])->name('export');
        Route::middleware('can:news.create')->group(function () {
            Route::post('/', [NewsArticleController::class, 'store'])->name('store');
        });

        Route::get('/{article}/edit', [NewsArticleController::class, 'edit'])->name('edit');
        // Sesudah `/create`, dan dibatasi angka. Tanpa `whereNumber`, `{article}`
        // menelan setiap ruas — termasuk `categories`, yang route-nya memang
        // sudah didaftarkan lebih dulu tapi tidak akan selamat kalau urutannya
        // suatu saat berubah.
        Route::get('/{article}', [NewsArticleController::class, 'show'])
            ->whereNumber('article')->name('show');
        Route::middleware('can:news.update')->group(function () {
            Route::put('/{article}', [NewsArticleController::class, 'update'])->name('update');
            // Dua sakelar cepat dari daftar. Terpisah dari `update` karena
            // aturannya berbeda: `update` menuntut seluruh formulir valid,
            // sedangkan menyalakan Highlight tidak boleh gagal karena
            // artikelnya kebetulan belum punya gambar hero. Izinnya tetap
            // sama — keduanya mengubah artikel yang sudah ada.
            Route::patch('/{article}/visibility', [NewsArticleController::class, 'visibility'])
                ->whereNumber('article')->name('visibility');
            Route::patch('/{article}/highlight', [NewsArticleController::class, 'highlight'])
                ->whereNumber('article')->name('highlight');
        });

        Route::middleware('can:news.delete')->group(function () {
            Route::delete('/{article}', [NewsArticleController::class, 'destroy'])->name('destroy');
        });
    });

    // -------------------------------------------------------- Newsletter
    Route::middleware('can:newsletter.view')->group(function () {
        Route::get('/newsletter', [NewsletterController::class, 'index'])->name('newsletter.index');
        // Sebelum `/{subscriber}`, dan `{subscriber}` dibatasi angka.
        Route::get('/newsletter/export', [NewsletterController::class, 'export'])->name('newsletter.export');

        Route::middleware('can:newsletter.update')->group(function () {
            Route::patch('/newsletter/{subscriber}/status', [NewsletterController::class, 'status'])
                ->whereNumber('subscriber')->name('newsletter.status');
        });

        Route::middleware('can:newsletter.delete')->group(function () {
            Route::delete('/newsletter/{subscriber}', [NewsletterController::class, 'destroy'])
                ->whereNumber('subscriber')->name('newsletter.destroy');
        });
    });

    // -------------------------------------------------- Integrity Reports
    Route::middleware('can:integrity-reports.view')->group(function () {
        Route::get('/integrity-reports', [IntegrityReportController::class, 'index'])
            ->name('integrity-reports.index');
        Route::get('/integrity-reports/export', [IntegrityReportController::class, 'export'])
            ->name('integrity-reports.export');
        Route::get('/integrity-reports/{integrityReport}', [IntegrityReportController::class, 'show'])
            ->whereNumber('integrityReport')->name('integrity-reports.show');

        Route::middleware('can:integrity-reports.delete')->group(function () {
            Route::delete('/integrity-reports/{integrityReport}', [IntegrityReportController::class, 'destroy'])
                ->whereNumber('integrityReport')->name('integrity-reports.destroy');
        });
    });

    // ----------------------------------------------------------- Home Page
    Route::middleware('can:home.view')->group(function () {
        Route::get('/home-page', [HomePageController::class, 'edit'])->name('home-page.edit');

        Route::middleware('can:home.update')->group(function () {
            Route::put('/home-page', [HomePageController::class, 'update'])->name('home-page.update');
        });
    });

    // ----------------------------------------------------------------- FAQ
    Route::prefix('faq/categories')->name('faq.categories.')->middleware('can:faq.view')->group(function () {
        Route::get('/', [FaqCategoryController::class, 'index'])->name('index');
        Route::get('/create', [FaqCategoryController::class, 'create'])->name('create');
        Route::middleware('can:faq.create')->group(function () {
            Route::post('/', [FaqCategoryController::class, 'store'])->name('store');
        });

        Route::get('/{category}/edit', [FaqCategoryController::class, 'edit'])->name('edit');

        Route::middleware('can:faq.update')->group(function () {
            Route::put('/{category}', [FaqCategoryController::class, 'update'])->name('update');
        });

        Route::middleware('can:faq.delete')->group(function () {
            Route::delete('/{category}', [FaqCategoryController::class, 'destroy'])->name('destroy');
        });
    });

    Route::prefix('faq')->name('faq.')->middleware('can:faq.view')->group(function () {
        Route::get('/', [FaqController::class, 'index'])->name('index');
        Route::get('/create', [FaqController::class, 'create'])->name('create');
        Route::middleware('can:faq.create')->group(function () {
            Route::post('/', [FaqController::class, 'store'])->name('store');
        });

        Route::get('/manage', [FaqController::class, 'manage'])->name('manage');
        // "FAQ per Halaman" — memilih dan mengurutkan isi tiap halaman publik.
        // Terpisah dari `/faq/manage`, yang mengurutkan halaman FAQ lengkap.
        Route::get('/pages', [FaqController::class, 'pages'])->name('pages');
        Route::get('/export', [FaqController::class, 'export'])->name('export');

        // Mengurutkan dan menempatkan adalah MENGUBAH, walau tidak lewat
        // formulir mana pun.
        Route::middleware('can:faq.update')->group(function () {
            Route::put('/pages', [FaqController::class, 'placements'])->name('pages.save');
            Route::put('/reorder', [FaqController::class, 'reorder'])->name('reorder');
        });
        Route::get('/{faq}/edit', [FaqController::class, 'edit'])->name('edit');
        // `whereNumber` di tiap route ber-parameter: tanpa itu `{faq}` menelan
        // setiap ruas, termasuk `manage` dan `export` di atas kalau suatu saat
        // urutannya berubah.
        Route::get('/{faq}', [FaqController::class, 'show'])->whereNumber('faq')->name('show');
        Route::middleware('can:faq.update')->group(function () {
            Route::put('/{faq}', [FaqController::class, 'update'])->whereNumber('faq')->name('update');
            Route::patch('/{faq}/status', [FaqController::class, 'status'])
                ->whereNumber('faq')->name('status');
        });

        Route::middleware('can:faq.delete')->group(function () {
            Route::delete('/{faq}', [FaqController::class, 'destroy'])->whereNumber('faq')->name('destroy');
        });
    });

    // ------------------------------------------------------- Press Releases
    // --------------------------------------------- Federations & Members
    Route::middleware('can:federations.view')->group(function () {
        // Sebelum route resource: `{federation}` di dalamnya menelan setiap ruas.
        Route::get('/federations/export', [MemberFederationController::class, 'export'])
            ->name('federations.export');

        // Statistik memakai izin modul yang sama: angkanya bicara tentang
        // keanggotaan, dan memisahkannya berarti satu menu lagi untuk enam baris.
        Route::get('/federations/stats', [FederationStatController::class, 'index'])
            ->name('federations.stats');
        Route::put('/federations/stats', [FederationStatController::class, 'update'])
            ->middleware('can:federations.update')->name('federations.stats.update');

        Route::patch('/federations/{federation}/status', [MemberFederationController::class, 'status'])
            ->whereNumber('federation')
            ->middleware('can:federations.update')->name('federations.status');

        Route::resource('federations', MemberFederationController::class)
            ->except('show')
            ->whereNumber('federation')
            ->middlewareFor(['create', 'store'], 'can:federations.create')
            ->middlewareFor(['edit', 'update'], 'can:federations.update')
            ->middlewareFor('destroy', 'can:federations.delete');
    });

    // ----------------------------------------------------- SEO & Social
    // Izin `settings.*` yang sudah ada, bukan modul baru: ia bagian dari grup
    // Site Settings, dan yang boleh mengubah kontak federasi adalah orang yang
    // sama dengan yang boleh mengubah judul halamannya.
    Route::middleware('can:settings.view')->group(function () {
        Route::get('/seo-social', [SeoController::class, 'index'])->name('seo-social');

        Route::middleware('can:settings.update')->group(function () {
            // `create` didaftarkan SEBELUM `{page}`, dan `{page}` dibatasi
            // angka: tanpa keduanya "create" terbaca sebagai id halaman.
            Route::get('/seo-social/create', [SeoController::class, 'create'])
                ->name('seo-social.create');
            Route::get('/seo-social/{page}/edit', [SeoController::class, 'edit'])
                ->whereNumber('page')->name('seo-social.edit');

            // POST, bukan PUT: `PUT` tidak membawa berkas, dan gambar OG berkas.
            Route::post('/seo-social', [SeoController::class, 'store'])->name('seo-social.store');
            Route::post('/seo-social/{page}', [SeoController::class, 'update'])
                ->whereNumber('page')->name('seo-social.update');
            Route::delete('/seo-social/{page}', [SeoController::class, 'destroy'])
                ->whereNumber('page')->name('seo-social.destroy');
        });
    });

    // ------------------------------------------------- People & Governance
    Route::middleware('can:people.view')->group(function () {
        Route::get('/people', [PeopleController::class, 'index'])->name('people.index');
        Route::get('/people/sub-committees', [PeopleController::class, 'subCommittees'])
            ->name('people.sub-committees');
        Route::get('/people/committees', [PeopleController::class, 'committees'])->name('people.committees');

        Route::middleware('can:people.update')->group(function () {
            Route::put('/people/sub-committees', [PeopleController::class, 'updateSubCommittees'])
                ->name('people.sub-committees.update');
            Route::put('/people/committees', [PeopleController::class, 'updateCommittees'])
                ->name('people.committees.update');
            // POST, bukan PUT: `PUT` tidak membawa berkas, dan potretnya berkas.
            Route::post('/people/{member}', [PeopleController::class, 'updateMember'])
                ->whereNumber('member')->name('people.members.update');
        });

        Route::post('/people', [PeopleController::class, 'storeMember'])
            ->middleware('can:people.create')->name('people.members.store');
        Route::delete('/people/{member}', [PeopleController::class, 'destroyMember'])
            ->whereNumber('member')->middleware('can:people.delete')->name('people.members.destroy');
    });

    // ------------------------------------------------- Partners & Heritage
    Route::middleware('can:blocks.view')->group(function () {
        Route::get('/blocks', [BlockController::class, 'partners'])->name('blocks.partners');
        Route::get('/blocks/heritage', [BlockController::class, 'heritage'])->name('blocks.heritage');

        Route::middleware('can:blocks.create')->group(function () {
            Route::post('/blocks/partners', [BlockController::class, 'storePartner'])
                ->name('blocks.partners.store');
            Route::post('/blocks/heritage', [BlockController::class, 'storeMilestone'])
                ->name('blocks.heritage.store');
        });

        Route::middleware('can:blocks.update')->group(function () {
            Route::post('/blocks/partners/{partner}', [BlockController::class, 'updatePartner'])
                ->whereNumber('partner')->name('blocks.partners.update');
            Route::post('/blocks/heritage/{milestone}', [BlockController::class, 'updateMilestone'])
                ->whereNumber('milestone')->name('blocks.heritage.update');
        });

        Route::middleware('can:blocks.delete')->group(function () {
            Route::delete('/blocks/partners/{partner}', [BlockController::class, 'destroyPartner'])
                ->whereNumber('partner')->name('blocks.partners.destroy');
            Route::delete('/blocks/heritage/{milestone}', [BlockController::class, 'destroyMilestone'])
                ->whereNumber('milestone')->name('blocks.heritage.destroy');
        });
    });

    // -------------------------------------------------- Results & Winners
    Route::middleware('can:results.view')->group(function () {
        Route::get('/results', [ResultController::class, 'index'])->name('results.index');

        // Champions dan Olympic SEBELUM `{tournament}`, yang menelan setiap ruas.
        Route::get('/results/champions', [ResultController::class, 'champions'])->name('results.champions');
        Route::get('/results/olympic', [ResultController::class, 'olympic'])->name('results.olympic');
        Route::get('/results/olympic/export', [ResultController::class, 'exportOlympic'])
            ->name('results.olympic.export');

        Route::middleware('can:results.update')->group(function () {
            Route::put('/results/olympic', [ResultController::class, 'updateOlympic'])
                ->name('results.olympic.update');
            Route::post('/results/champions', [ResultController::class, 'storeChampion'])
                ->middleware('can:results.create')->name('results.champions.store');
            Route::post('/results/champions/{champion}', [ResultController::class, 'updateChampion'])
                ->whereNumber('champion')->name('results.champions.update');
            Route::delete('/results/champions/{champion}', [ResultController::class, 'destroyChampion'])
                ->whereNumber('champion')->middleware('can:results.delete')->name('results.champions.destroy');
            Route::post('/results/{tournament}', [ResultController::class, 'update'])
                ->whereNumber('tournament')->name('results.winners.update');
        });

        Route::get('/results/{tournament}', [ResultController::class, 'edit'])
            ->whereNumber('tournament')->name('results.winners');
    });

    // ------------------------------------------------------- Tournaments
    Route::middleware('can:tournaments.view')->group(function () {
        // Sebelum route resource: `{tournament}` di dalamnya menelan setiap ruas.
        Route::get('/tournaments/export', [TournamentController::class, 'export'])
            ->name('tournaments.export');
        // Daftar alamat "Notify me" satu turnamen. `tournaments.update`, bukan
        // `.view`: mengunduh daftar alamat orang adalah tindakan, bukan
        // pembacaan. Dicatat di jejak audit oleh controllernya.
        Route::middleware('can:tournaments.update')->group(function () {
            Route::get('/tournaments/{tournament}/notifications/export', [TournamentController::class, 'exportNotifications'])
                ->whereNumber('tournament')->name('tournaments.notifications.export');
        });

        Route::middleware('can:tournaments.update')->group(function () {
            Route::patch('/tournaments/{tournament}/visibility', [TournamentController::class, 'visibility'])
                ->whereNumber('tournament')->name('tournaments.visibility');
        });

        Route::resource('tournaments', TournamentController::class)
            ->except('show')
            ->whereNumber('tournament')
            ->middlewareFor(['create', 'store'], 'can:tournaments.create')
            ->middlewareFor(['edit', 'update'], 'can:tournaments.update')
            ->middlewareFor('destroy', 'can:tournaments.delete');
    });

    Route::middleware('can:documents.view')->group(function () {
        // `export` didaftarkan SEBELUM route resource: `show` di dalamnya
        // memakai `{document}` yang menelan setiap ruas.
        Route::get('/documents/export', [DocumentController::class, 'export'])
            ->name('documents.export');
        Route::middleware('can:documents.update')->group(function () {
            Route::patch('/documents/{document}/visibility', [DocumentController::class, 'visibility'])
                ->whereNumber('document')->name('documents.visibility');
        });

        // `middlewareFor` per aksi. Satu `Route::resource()` di dalam grup
        // ber-`.view` saja mendaftarkan tulis dengan izin baca — itu yang
        // membuat `viewer` bisa menghapus, tanpa satu pun galat.
        Route::resource('documents', DocumentController::class)
            ->whereNumber('document')
            ->middlewareFor(['create', 'store'], 'can:documents.create')
            ->middlewareFor(['edit', 'update'], 'can:documents.update')
            ->middlewareFor('destroy', 'can:documents.delete');
    });

    // -------------------------------------------------------------- Gallery
    Route::middleware('can:gallery.view')->group(function () {
        // Sebelum route resource: `{gallery}` di dalamnya menelan setiap ruas.
        Route::get('/gallery/export', [GalleryController::class, 'export'])->name('gallery.export');
        Route::middleware('can:gallery.update')->group(function () {
            Route::patch('/gallery/{gallery}/visibility', [GalleryController::class, 'visibility'])
                ->whereNumber('gallery')->name('gallery.visibility');
        });

        // Tanpa `show`: galeri sudah MENAMPILKAN isinya di kisi — layar baca
        // terpisah untuk satu gambar cuma memperbesar gambar yang sama.
        Route::resource('gallery', GalleryController::class)
            ->except('show')
            ->whereNumber('gallery')
            ->middlewareFor(['create', 'store'], 'can:gallery.create')
            ->middlewareFor(['edit', 'update'], 'can:gallery.update')
            ->middlewareFor('destroy', 'can:gallery.delete');
    });

    // ---------------------------------------------------------- Legal pages
    Route::middleware('can:legal-pages.view')->group(function () {
        Route::get('/legal-pages', [LegalPageController::class, 'index'])->name('legal-pages.index');
        Route::get('/legal-pages/{key}', [LegalPageController::class, 'edit'])->name('legal-pages.edit');
        Route::middleware('can:legal-pages.update')->group(function () {
            Route::put('/legal-pages/{key}', [LegalPageController::class, 'update'])->name('legal-pages.update');
        });
    });

    // ----------------------------------------------------- Contact & Social
    Route::middleware('can:settings.view')->group(function () {
        Route::get('/contact-social', [ContactSettingController::class, 'edit'])->name('contact-social.edit');
        Route::middleware('can:settings.update')->group(function () {
            Route::put('/contact-social', [ContactSettingController::class, 'update'])->name('contact-social.update');
        });
    });

    // ------------------------------------------------------ Contact Messages
    Route::middleware('can:contact-messages.view')->group(function () {
        Route::get('/contact-messages', [ContactMessageController::class, 'index'])->name('contact-messages.index');
        // Sebelum `{contactMessage}`, yang menelan setiap ruas.
        Route::get('/contact-messages/export', [ContactMessageController::class, 'export'])
            ->name('contact-messages.export');
        Route::get('/contact-messages/{contactMessage}', [ContactMessageController::class, 'show'])
            ->whereNumber('contactMessage')->name('contact-messages.show');
        Route::middleware('can:contact-messages.delete')->group(function () {
            Route::delete('/contact-messages/{contactMessage}', [ContactMessageController::class, 'destroy'])
                ->whereNumber('contactMessage')->name('contact-messages.destroy');
        });
    });

    // ------------------------------------------------------ User Management
    // Izin per aksi, bukan satu `can:users.view` untuk semuanya: peran yang
    // boleh MELIHAT daftar pengguna belum tentu boleh membuat atau menghapus.
    // Sebelum route resource: `{user}` di dalamnya menelan setiap ruas.
    Route::get('/users/export', [UserController::class, 'export'])
        ->middleware('can:users.view')->name('users.export');

    Route::patch('/users/{user}/status', [UserController::class, 'status'])
        ->whereNumber('user')->middleware('can:users.update')->name('users.status');

    // Mengirim ulang dan mencabut undangan — "can be resent or revoked from
    // Admin Users" (`529:9716`). Keduanya `users.update`, bukan `users.create`:
    // akunnya sudah ada, yang diterbitkan ulang cuma tautannya.
    Route::post('/users/{user}/invitation/resend', [UserController::class, 'resendInvitation'])
        ->whereNumber('user')->middleware('can:users.update')->name('users.invitation.resend');
    Route::delete('/users/{user}/invitation', [UserController::class, 'revokeInvitation'])
        ->whereNumber('user')->middleware('can:users.update')->name('users.invitation.revoke');

    Route::resource('users', UserController::class)
        ->except('show')
        ->middleware('can:users.view')
        ->middlewareFor(['create', 'store'], 'can:users.create')
        ->middlewareFor(['edit', 'update'], 'can:users.update')
        ->middlewareFor('destroy', 'can:users.delete');

    // Peran memakai izin `users.*` yang sama: siapa yang boleh mengatur
    // pengguna, boleh mengatur peran. Memisahkannya berarti seseorang bisa
    // memberi dirinya izin lewat peran tanpa boleh menyentuh pengguna.
    Route::resource('roles', RoleController::class)
        ->except('show')
        ->middleware('can:users.view')
        ->middlewareFor(['create', 'store'], 'can:users.create')
        ->middlewareFor(['edit', 'update'], 'can:users.update')
        ->middlewareFor('destroy', 'can:users.delete');

    // ---------------------------------------------------------- IP Whitelist
    // Izin sendiri, BUKAN `users.*`. Siapa yang boleh menentukan dari alamat
    // mana orang bisa masuk adalah pertanyaan yang berbeda dari siapa yang
    // boleh membuat akun — dan yang pertama bisa membatalkan seluruh sisanya.
    // Sebelum route resource: `{ip_whitelist}` di dalamnya menelan setiap ruas.
    Route::get('/ip-whitelist/export', [IpWhitelistRuleController::class, 'export'])
        ->middleware('can:ip-whitelist.view')->name('ip-whitelist.export');

    Route::patch('/ip-whitelist/{ip_whitelist}/status', [IpWhitelistRuleController::class, 'status'])
        ->whereNumber('ip_whitelist')
        ->middleware('can:ip-whitelist.update')->name('ip-whitelist.status');

    Route::resource('ip-whitelist', IpWhitelistRuleController::class)
        ->except('show')
        ->parameters(['ip-whitelist' => 'ip_whitelist'])
        ->middleware('can:ip-whitelist.view')
        ->middlewareFor(['create', 'store'], 'can:ip-whitelist.create')
        ->middlewareFor(['edit', 'update'], 'can:ip-whitelist.update')
        ->middlewareFor('destroy', 'can:ip-whitelist.delete');

    // --------------------------------------------------------- Activity Log
    Route::middleware('can:activity-log.view')->group(function () {
        Route::get('/activity-log', [ActivityLogController::class, 'index'])->name('activity-log.index');
        // Jejak audit yang tidak bisa dibawa keluar hanya berguna selama orang
        // yang membutuhkannya duduk di depan layar ini.
        Route::get('/activity-log/export', [ActivityLogController::class, 'export'])
            ->name('activity-log.export');
    });

    /*
     * Tidak ada lagi route placeholder — SETIAP tujuan sidebar sekarang punya
     * layarnya sendiri, dan `NavigationTest` gagal kalau ada yang ditambahkan
     * tanpa itu.
     *
     * Dulu menu yang modulnya belum ada mendapat halaman kosong alih-alih 404.
     * Niatnya baik, akibatnya tidak: menu yang berujung halaman kosong
     * mengajari orang bahwa sebagian sidebar memang tidak berfungsi, dan
     * sesudah itu yang berfungsi pun ikut tidak dicoba. Tiga kelompok terakhir
     * yang memakainya — Header & Navigation, Footer, dan Landing Page —
     * semuanya berakhir dibuang atau diganti layar sungguhan.
     */
});
