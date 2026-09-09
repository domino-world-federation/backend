<?php

return [
    /*
     * Akun admin pertama, dipakai `DatabaseSeeder`. Nilainya dari `.env` —
     * kredensial tidak pernah ditulis di dalam kode.
     */
    'admin' => [
        'name' => env('DWF_ADMIN_NAME', 'DWF Admin'),
        'email' => env('DWF_ADMIN_EMAIL'),
        'password' => env('DWF_ADMIN_PASSWORD'),
    ],

    /*
     * Pilihan tetap untuk formulir Add Tournament (`585:11241`).
     *
     * Desain menggambar tujuh dropdown tanpa menuliskan isinya. Daftarnya
     * ditaruh di sini, bukan di tabel, dengan alasan yang sama seperti
     * `document_categories` di bawah: wireframe tidak punya layar CRUD untuk
     * satu pun di antaranya, dan membuatkannya berarti mengarang tujuh menu
     * yang tidak diminta.
     *
     * NILAINYA yang disimpan di database, bukan indeksnya — jadi menambah
     * pilihan aman, sedangkan mengganti tulisan yang sudah dipakai akan
     * membuat baris lama tidak cocok dengan daftar ini lagi.
     */
    'tournaments' => [
        // Tingkat kompetisi yang tercetak di dekat judul turnamen.
        // Nilainya diselaraskan dengan `Tournament.category` di mock situs
        // publik (`../landing-page-nuxt/app/lib/api/mock/index.ts`) — kolom itu
        // yang tercetak sebagai baris kecil di atas nama turnamen, jadi daftar
        // yang berbeda berarti kartu publik menampilkan istilah yang tidak
        // pernah dipakai backoffice.
        'coverage' => [
            'Inter-continental',
            'Championship',
            'Regional qualifier',
            'Continental',
            'National',
            'Invitational',
        ],

        /*
         * "Tournament Rules Format" — aturan main, dan apa yang mengikutinya.
         *
         * Kuncinya yang TERSIMPAN di `tournaments.rules_format`; sisanya
         * diturunkan darinya dan tidak pernah diketik siapa pun:
         *
         *   `side`  — menentukan apakah pesertanya pemain atau tim, dan karena
         *             itu menentukan label kolom jumlah peserta beserta pilihan
         *             angkanya. Ditulis eksplisit, bukan ditebak dari kata
         *             "Single"/"Double" di judulnya: judul adalah teks yang
         *             boleh berubah, `side` adalah sifat aturannya.
         *   `scoring` dan `competition_system` — kalimat yang tercetak di
         *             halaman turnamen. Dulu dua kolom teks bebas yang diisi
         *             tangan per turnamen, padahal isinya sifat ATURANNYA:
         *             enam turnamen dengan aturan yang sama menghasilkan enam
         *             kalimat yang berbeda-beda susunannya.
         *
         * `$n` diganti jumlah peserta, dan `($n / k)` dihitung — lihat
         * `TournamentRules::render()`. Ia BUKAN `eval`: hanya pola itu yang
         * dikenali, karena naskah ini akan tercetak di situs publik.
         */
        'rules_formats' => [
            'Double 101' => [
                'side' => 'double',
                'scoring' => 'First team to reach 101 points wins the match.',
                'competition_system' => '$n-team knockout bracket with ($n / 2) opening-round matches. Each match consists of two teams (four players).',
            ],
            'Single 101' => [
                'side' => 'single',
                'scoring' => 'First player to reach 101 points wins the match.',
                'competition_system' => '$n-player knockout format with ($n / 4) opening-round groups. Each group consists of four players, and the winner advances to the next stage.',
            ],
            'Double Knockout' => [
                'side' => 'double',
                'scoring' => 'The team that wins the round wins the match.',
                'competition_system' => '$n-team knockout bracket with ($n / 2) opening-round matches. Each match consists of two teams (four players).',
            ],
            'Single Knockout' => [
                'side' => 'single',
                'scoring' => 'The player who wins the round wins the match.',
                'competition_system' => '$n-player knockout format with ($n / 4) opening-round groups. Each group consists of four players, and the winner advances to the next stage.',
            ],
            'Double BO3' => [
                'side' => 'double',
                'scoring' => 'The first team to win two rounds wins the match.',
                'competition_system' => '$n-team knockout bracket with ($n / 2) opening-round matches. Each match consists of two teams (four players).',
            ],
            'Single BO3' => [
                'side' => 'single',
                'scoring' => 'The first player to win two rounds wins the match.',
                'competition_system' => '$n-player knockout format with ($n / 4) opening-round groups. Each group consists of four players, and the winner advances to the next stage.',
            ],
        ],

        /*
         * Jumlah peserta yang boleh dipilih, per `side`.
         *
         * Daftar tertutup, bukan angka bebas: babak gugur hanya bekerja pada
         * pangkat dua, dan `($n / 2)`/`($n / 4)` di kalimat sistem kompetisi
         * hanya bulat pada angka-angka ini. Sebuah turnamen 100 tim akan
         * mencetak "50 opening-round matches" untuk bagan yang tidak bisa
         * disusun.
         *
         * Single mulai dari 16 karena babaknya berkelompok empat; double dari 8
         * karena babaknya berpasangan dua.
         */
        'participant_counts' => [
            'single' => [16, 64, 256, 1024],
            'double' => [8, 16, 32, 64, 128, 256, 512, 1024],
        ],

        /* Label peserta, diturunkan dari `side` — tidak lagi dipilih tangan. */
        'participant_types' => ['single' => 'Players', 'double' => 'Teams'],

        /*
         * Pil di sebelah kategori di kartu publik (`592:16886`).
         *
         * Bukan lagi pilihan: seluruh turnamen federasi ini digelar langsung,
         * jadi dropdown-nya dicabut dari layar atas permintaan pemilik repo
         * 2026-09-07 dan nilainya dipatok. Ia tetap TERSIMPAN dan tetap dikirim
         * ke situs publik — pilnya digambar desain, dan "Offline" adalah
         * keterangan yang benar, bukan sisa.
         *
         * Kalau suatu hari ada acara daring, yang dikembalikan dropdown-nya;
         * kolomnya tidak perlu disentuh.
         */
        'attendance_default' => 'Offline',

        'currencies' => ['USD', 'EUR', 'GBP', 'CHF', 'IDR'],

        'dwf_id_requirements' => [
            'Required for all participants',
            'Required for team captains only',
            'Not required',
        ],

        'eligibility' => [
            'Open to all DWF member federations',
            'Invited federations only',
            'National champions only',
            'Open to all registered players',
        ],

        'registration_methods' => [
            'Through national federation',
            'Direct online registration',
            'By invitation only',
        ],

        // "select up to 10 existing published documents" (`596:11467`).
        'max_documents' => 10,
    ],

    /*
     * Tingkat keanggotaan federasi.
     *
     * NILAINYA (`continent`, `national`, …) harus tetap sama dengan
     * `MEMBERSHIP_TIERS` di `../landing-page-nuxt/app/content/members/index.ts`:
     * situs publik memakai id itu untuk memilih warna gradien tiap tingkat, dan
     * id yang tidak dikenalinya menghasilkan kartu tanpa warna — tanpa galat.
     *
     * Warnanya sendiri TIDAK ikut ke sini. Ia keputusan tampilan milik situs
     * publik, dan menyalinnya ke backoffice berarti dua tempat yang harus
     * diubah bersamaan setiap desainer menggeser satu gradien.
     */
    'membership_tiers' => [
        'continent' => 'Continent Members',
        'national' => 'National Members',
        'regional' => 'Regional Members',
        'club' => 'Club Members',
    ],

    /*
     * Kategori dokumen, dan di halaman mana tiap kategori muncul.
     *
     * Daftar tetap, bukan tabel: wireframe tidak punya layar CRUD untuk
     * kategori dokumen, dan membuatkannya berarti mengarang menu yang tidak
     * diminta. Kalau nanti perlu dikelola sendiri, pindahkan ke tabel dan
     * jaga nilainya tetap sama supaya baris lama tidak yatim.
     *
     * ── Kuncinya adalah yang TERSIMPAN, dan yang tayang ──
     *
     * Nilai kolom `documents.category` adalah kunci di bawah ini apa adanya,
     * dan situs publik menyaring dengan string yang sama persis
     * (`getResources("Rules & Regulations")`). Ia juga DICETAK: kartu dokumen
     * menampilkannya sebagai baris kelabu kecil di atas judul. Jadi mengubah
     * ejaan satu kunci berarti tiga hal sekaligus — baris lama jadi yatim,
     * section di situs publik jadi kosong, dan tulisan di kartu ikut berubah.
     * Ganti ejaan HANYA lewat migrasi yang ikut memindahkan barisnya, seperti
     * `2026_09_05_150000_remap_document_categories` dan
     * `2026_09_09_100000_rename_document_categories`.
     *
     * ── Daftar halamannya bukan hiasan ──
     *
     * Ia dikirim ke layar Documents dan dicetak di bawah dropdown-nya, supaya
     * orang yang mengunggah tahu di mana berkasnya akan muncul SEBELUM
     * menyimpan. Sebelum ini kolom Category tidak memberi petunjuk apa pun
     * tentang akibat memilihnya.
     *
     * Home menarik dokumen terbaru TANPA menyaring kategori, jadi setiap
     * kategori sampai ke sana — itu sebabnya semuanya menyebut Home.
     *
     * ── Kategori menentukan KELAYAKAN, section menentukan yang TAYANG ──
     *
     * Sejak layar "Documents per Halaman" ada (D78), kategori tidak lagi
     * langsung menentukan isi sebuah rak. Ia menentukan dokumen mana yang BOLEH
     * dipilih untuk rak itu; yang benar-benar tayang adalah yang dipilih admin
     * di `document_placements`. Rak yang belum pernah disentuh jatuh kembali ke
     * "N terbaru dari kategori ini", jadi daftar di bawah tetap menjawab
     * pertanyaan "berkas saya muncul di mana".
     *
     * Peta section → kategori → batas ada di `document_sections`, di bawah.
     *
     * ── `planned` sudah tidak dipakai, dan itu kabar baik ──
     *
     * Dulu ada kunci kedua, `planned`, untuk halaman yang DIMINTA menampilkan
     * sebuah kategori tapi belum punya rak sama sekali — Integrity, Members,
     * dan About Us, lewat kategori `Integrity & Ethics` dan `Membership
     * Documents`. Kedua kategori itu dihapus 2026-09-09 atas permintaan pemilik
     * repo, jadi tidak ada lagi yang perlu dijanjikan setengah-setengah.
     * `DocumentCategories::options()` masih membaca `planned` kalau suatu saat
     * ada yang membutuhkannya lagi.
     */
    'document_categories' => [
        'Rules & Regulations' => [
            'pages' => ['Domino', 'Tournaments', 'Home'],
        ],
        'Governance Documents' => [
            'pages' => ['Governance', 'Home'],
        ],
        'Development Resources' => [
            'pages' => ['Development', 'Home'],
        ],

        /*
         * "Tournament Detail", dan sejak 2026-09-09 itu memang tepat.
         *
         * Dulu baris ini menyebut "Tournaments" dengan peringatan panjang bahwa
         * halaman DETAIL sebuah turnamen memakai mekanisme lain — lampiran
         * lewat `document_tournament`, apa pun kategorinya — sehingga menulis
         * "Tournament Detail" di sini akan menyesatkan pengunggah.
         *
         * Yang membuat peringatan itu tidak berlaku lagi: picker lampiran di
         * layar Tournaments sekarang HANYA menawarkan dokumen berkategori ini.
         * Jadi kategori ini memang syarat untuk bisa dilampirkan, dan halaman
         * detail memang tempatnya tayang. Rak di halaman DAFTAR turnamen
         * pindah ke `Rules & Regulations` (section `tournaments.regulations`).
         */
        'Tournament Documents' => [
            'pages' => ['Tournament Detail', 'Home'],
        ],

        /*
         * Dua kategori untuk halaman News, dan itu memang perlu.
         *
         * News punya DUA rak dokumen yang digambar desainer — Press Releases
         * (`1010:2701`) dan Publications (`1010:2742`) — dan satu kategori
         * tidak bisa mengisi keduanya tanpa menampilkan isi yang sama dua kali.
         *
         * Ejaannya dipendekkan 2026-09-09 atas permintaan pemilik repo:
         * "Media & Press Releases" jadi "Press Releases", "Reports &
         * Publications" jadi "Publication". Barisnya ikut pindah lewat
         * `2026_09_09_100000_rename_document_categories`.
         */
        'Press Releases' => [
            'pages' => ['News', 'Home'],
        ],
        'Publication' => [
            'pages' => ['News', 'Home'],
        ],
    ],

    /*
     * Rak dokumen di situs publik — satu baris per rak yang bisa dikurasi.
     *
     * Ini katalog untuk layar "Documents per Halaman" (D78). Sebelumnya tiap
     * rak menarik sendiri "N terbaru dari kategori X", jadi tidak ada seorang
     * pun yang bisa memutuskan dokumen MANA yang tampil di mana — dan dua rak
     * yang kebetulan menarik kategori yang sama menampilkan isi yang sama
     * persis. Itu keadaan Governance: Statutes & Constitution dan Governance
     * Repository dua-duanya `Governance Documents`.
     *
     * ── Kuncinya kontrak antar-repo, seperti nama kategori ──
     *
     * `key` di bawah dikirim mentah-mentah oleh situs publik
     * (`/api/v1/resources?section=news.publications`) dan disimpan di kolom
     * `document_placements.section`. Menggantinya menuntut tiga hal sekaligus,
     * sama seperti mengganti ejaan kategori: migrasi yang memindahkan baris
     * `document_placements`, perubahan di `landing-page-nuxt`, dan penyesuaian
     * `DocumentSectionTest` yang mengejanya lengkap.
     *
     * ── `category` null berarti "seluruh perpustakaan" ──
     *
     * Hanya Home yang begitu: rak Resource Library-nya memang tidak menyaring
     * kategori. Sisanya membatasi pilihan admin ke satu kategori, sehingga
     * picker tidak menawarkan dokumen yang — kalau dipilih — akan tampil di
     * rak yang salah tempat.
     *
     * ── `max` adalah batas rak, bukan batas kategori ──
     *
     * Dua rak bernilai 1 (`domino.rulebook`, `development.youth`) karena
     * desainnya memang menggambar SATU dokumen di sana: kartu Official Rulebook
     * dan tombol kurikulum. Rak yang menggambar grid memakai 6.
     *
     * ── Rak yang belum dikurasi tidak kosong ──
     *
     * `PublicController::resources()` jatuh kembali ke "N terbaru dari
     * kategori ini" untuk section yang belum punya satu pun baris di
     * `document_placements`. Tanpa itu, hari fitur ini menyala adalah hari
     * seluruh rak dokumen di situs publik mendadak kosong sampai ada yang
     * sempat mengisi sembilan-sembilannya.
     *
     * ── Yang TIDAK ada di sini, dan sebabnya ──
     *
     * Halaman detail turnamen menampilkan dokumen yang DILAMPIRKAN ke event itu
     * dari layar Tournaments, bukan rak yang dikurasi terpisah — mekanismenya
     * `document_tournament`, dan lampirannya memang milik turnamen, bukan milik
     * halaman. Yang berubah 2026-09-09 hanya pilihannya: picker itu sekarang
     * disaring ke kategori `Tournament Documents`.
     *
     * Arsip press (`/news/press-releases`) juga tidak di sini. Ia memang
     * memperlihatkan SELURUH kategori `Press Releases` tanpa batas — itu arti
     * kata "archive", dan mengurasinya berarti sebuah arsip yang tidak lengkap.
     */
    'document_sections' => [
        'home.resources' => [
            'page' => 'Home',
            'label' => 'Resource Library',
            'category' => null,
            'max' => 6,
        ],
        'domino.rulebook' => [
            'page' => 'Domino',
            'label' => 'Referee Guidelines — Official Rulebook',
            'category' => 'Rules & Regulations',
            'max' => 1,
        ],
        'governance.statutes' => [
            'page' => 'Governance',
            'label' => 'Statutes & Constitution',
            'category' => 'Governance Documents',
            'max' => 6,
        ],
        'governance.repository' => [
            'page' => 'Governance',
            'label' => 'Governance Repository',
            'category' => 'Governance Documents',
            'max' => 6,
        ],
        'development.library' => [
            'page' => 'Development',
            'label' => 'Educational Resources',
            'category' => 'Development Resources',
            'max' => 6,
        ],
        'development.youth' => [
            'page' => 'Development',
            'label' => 'Youth Development',
            'category' => 'Development Resources',
            'max' => 1,
        ],
        'tournaments.regulations' => [
            'page' => 'Tournaments',
            'label' => 'Tournament Regulations',
            'category' => 'Rules & Regulations',
            'max' => 6,
        ],
        'news.press' => [
            'page' => 'News',
            'label' => 'Press Releases',
            'category' => 'Press Releases',
            'max' => 6,
        ],
        'news.publications' => [
            'page' => 'News',
            'label' => 'Publications',
            'category' => 'Publication',
            'max' => 6,
        ],
    ],

    /*
     * Pengalih bahasa di topbar. Mati secara bawaan — backoffice tampil dalam
     * satu bahasa (lihat `Locales::DEFAULT`). Nyalakan dengan
     * `DWF_LOCALE_SWITCHER=true` kalau nanti ada yang membutuhkannya.
     */
    'locale_switcher' => env('DWF_LOCALE_SWITCHER', false),

    /*
     * Otentikasi dua langkah (TOTP / Google Authenticator).
     *
     * Sakelar GLOBAL. Sakelar per pengguna ada di kolom
     * `users.two_factor_enabled` — itu yang nanti dikelola User Management.
     * Keduanya harus menyala agar 2FA diminta.
     */
    'two_factor' => env('DWF_TWO_FACTOR', true),

    /*
     * Jumlah baris per halaman, satu angka untuk seluruh daftar.
     *
     * Angka yang berbeda-beda per modul membuat orang kehilangan rasa
     * "seberapa jauh saya sudah menggulir" tiap kali berpindah layar.
     */
    'per_page' => 10,

    'uploads' => [
        /*
         * WebP saja, di SELURUH modul.
         *
         * Wireframe menyebut ".jpg .jpeg .png", tapi diminta WebP saja — dan
         * itu keputusan yang berdiri sendiri: gambar berita dan galeri tampil
         * di situs publik, dan WebP memangkas beratnya 25-35% pada mutu yang
         * sama. Satu format juga berarti satu jalur yang perlu diuji.
         *
         * Mime-nya diperiksa dari ISI berkas, bukan dari nama — `mimes:webp`
         * di Laravel membaca mime asli lewat fileinfo, jadi `.png` yang diganti
         * namanya jadi `.webp` tetap ditolak.
         */
        'image_mimes' => ['webp'],

        /*
         * 1 MB, bukan 2 MB seperti label di layar Add News (`252:4480`).
         *
         * Angkanya diturunkan karena satu fakta di situs publik: ia memakai
         * `provider: "none"` di `@nuxt/image` (CPU server produksinya di bawah
         * x86-64-v2, sharp menolak jalan), jadi TIDAK ADA yang mengecilkan
         * gambar. Byte yang diunggah adalah byte yang dikirim ke setiap
         * pengunjung.
         *
         * Diukur dengan `cwebp` pada gambar seperti-foto (derau, jadi ini batas
         * ATAS — foto sungguhan mengompres lebih baik):
         *
         *   1920 × 800  q82  412 KB   q95  679 KB
         *   3840 × 1600 q82 1634 KB   q95 2702 KB
         *   1600 × 900  q82  382 KB   q95  631 KB
         *   400 × 400   q82   43 KB   q95   72 KB
         *
         * Jadi 1 MB memuat hero 1920×800 pada mutu tertinggi dengan lapang,
         * dan MENOLAK 4K yang belum dikecilkan — yang memang benar ditolak
         * selama tidak ada pipeline: browser mengunduh seluruh 1,6 MB itu cuma
         * untuk menampilkannya pada 1920.
         *
         * Naikkan lagi begitu IPX hidup kembali; saat itu yang diunggah tidak
         * lagi sama dengan yang dikirim.
         */
        'image_max_kb' => 1024,

        // Batas bawah untuk gambar yang tidak punya slot berukuran tetap
        // (galeri, press release, kategori).
        'image_min_dimension' => 300,

        /*
         * Ukuran per slot, dibaca dari label di desain.
         *
         * `min_width`/`min_height` + `ratio`, BUKAN ukuran persis. Rasionya yang
         * menentukan tampilan — 3840×1600 memenuhi kotak hero sama baiknya
         * dengan 1920×800 dan lebih tajam di layar retina. Menolaknya berarti
         * memaksa orang MENGECILKAN gambar yang sudah benar.
         */
        'image_specs' => [
            'hero' => ['min_width' => 1920, 'min_height' => 800, 'ratio' => '12/5'],
            'landscape' => ['min_width' => 1600, 'min_height' => 900, 'ratio' => '16/9'],
        ],

        // Gambar yang disisipkan di dalam editor teks. Tanpa rasio: ia
        // ilustrasi di tengah tulisan, bentuknya memang bermacam-macam.
        'editor_image_min_dimension' => 200,

        // "PDF only. Recommended size up to 5 MB, maximum 10 MB."
        'document_mimes' => ['pdf'],
        'document_max_kb' => 10240,

        'video_mimes' => ['mp4', 'webm'],
        'video_max_kb' => 51200,
    ],

    // Halaman publik tempat FAQ bisa ditempelkan, beserta labelnya.
    'faq_pages' => [
        'home' => 'Home Page',
        'domino' => 'Domino Page',
        'tournament' => 'Tournament Page',
    ],
];
