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
         * Aturan main, dan apa yang mengikutinya.
         *
         * Kuncinya yang TERSIMPAN di `tournaments.tournament_mode` dan
         * `tournaments.domino_rules`; sisanya diturunkan darinya dan tidak
         * pernah diketik siapa pun:
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
        /*
         * Aturan main, DUA SUMBU — bukan satu daftar enam nama.
         *
         * Sampai 2026-09-23 ini satu field "Tournament Rules Format" dengan
         * enam pilihan: Single 101, Double 101, Single Knockout, Double
         * Knockout, Single BO3, Double BO3. Enam nama itu ternyata hasil silang
         * dua pertanyaan yang berdiri sendiri — berapa orang per sisi, dan
         * bagaimana satu pertandingan dimenangkan — dan menyatukannya memaksa
         * orang mengurai lagi "Double BO3" jadi dua keputusan setiap kali.
         * Sekarang keduanya ditanya terpisah (permintaan pemilik repo), dan
         * enam kombinasinya tetap enam yang sama.
         *
         * Kalimat penilaian lahir dari KEDUANYA, kalimat sistem kompetisi dari
         * modenya saja. Karena itu mode membawa `subject` dan `relative`: yang
         * pertama "player"/"team", yang kedua menjaga "the player WHO" dan "the
         * team THAT" tetap seperti sebelumnya — keduanya sudah tercetak di
         * halaman publik dan tidak ada alasan menggeser tata bahasanya.
         */
        'tournament_modes' => [
            'Single' => [
                'side' => 'single',
                'subject' => 'player',
                'relative' => 'who',
                'competition_system' => '$n-player knockout format with ($n / 4) opening-round groups. Each group consists of four players, and the winner advances to the next stage.',
            ],
            'Double' => [
                'side' => 'double',
                'subject' => 'team',
                'relative' => 'that',
                'competition_system' => '$n-team knockout bracket with ($n / 2) opening-round matches. Each match consists of two teams (four players).',
            ],
        ],

        /*
         * `Knockout` dan `BO3` berganti nama jadi `1 Round` dan `Double Win`
         * atas permintaan pemilik repo — nama yang dipakai federasi sendiri.
         * Mekanismenya tidak berubah: satu ronde menentukan, atau dua
         * kemenangan ronde yang menentukan.
         */
        'domino_rules' => [
            '101' => ['scoring' => 'First :subject to reach 101 points wins the match.'],
            '1 Round' => ['scoring' => 'The :subject :relative wins the round wins the match.'],
            'Double Win' => ['scoring' => 'The first :subject to win two rounds wins the match.'],
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

        /*
         * "Select Grand Prize Type" (`700:10891`) — kuncinya yang TERSIMPAN di
         * `tournaments.prize_type`, labelnya yang tercetak di dropdown.
         *
         * Jenisnya menentukan field mana yang ada di kartu Prize: `none` tidak
         * punya apa-apa lagi, `cash` menuntut mata uang + nominal + gambar,
         * `item` menuntut nama barang + gambar. Keterangan opsional di dua
         * yang terakhir. Aturannya di `TournamentRequest`.
         */
        'prize_types' => [
            'none' => 'No Prize',
            'cash' => 'Cash',
            'item' => 'Physical Item',
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
     * persis. Itu dulu keadaan Governance: Statutes & Constitution dan
     * Governance Repository dua-duanya `Governance Documents` — sampai revisi
     * 2026-09-28 menyatukan keduanya jadi satu rak, `governance.statutes`.
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
        // Tombol-tombol regulasi di bawah daftar tugas wasit (2026-09-30).
        // Dulu "sisa kategori" — tidak ada yang bisa memilih mana yang tampil
        // atau urutannya. Rulebook di kartu kiri tetap disaring keluar di situs,
        // supaya satu dokumen tidak tercetak dua kali.
        'domino.regulations' => [
            'page' => 'Domino',
            'label' => 'Referee Guidelines — Regulation Buttons',
            'category' => 'Rules & Regulations',
            'max' => 12,
        ],
        'governance.statutes' => [
            'page' => 'Governance',
            'label' => 'Governance Documents',
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

            /*
             * Avatar. Persegi, dan angkanya jauh lebih kecil dari dua di atas
             * karena tempat tayangnya memang kecil: 40px di blok akun sidebar,
             * 32px di topbar. 256 memberi ruang untuk layar retina dan untuk
             * ukuran yang lebih besar kalau suatu saat dipakai di layar profil
             * itu sendiri.
             *
             * `ratio` 1/1 ditegakkan, bukan dipotong otomatis: `object-cover`
             * di sidebar akan memotong foto lanskap tepat di tengah, dan yang
             * paling sering hilang di sana adalah kepala orangnya.
             */
            'avatar' => ['min_width' => 256, 'min_height' => 256, 'ratio' => '1/1'],
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

    /*
     * Editor halaman (`/pages`) — skema naskah tiap halaman publik.
     *
     * ── Kuncinya kontrak antar-repo ──
     *
     * `landing-page-nuxt` membaca nilai lewat `/api/v1/pages/{page}` dengan
     * kunci `section.field` (atau `section.list.N.field`), dan menandai
     * elemennya dengan kunci yang sama untuk klik-untuk-menyunting. Mengganti
     * kunci berarti nilai tersimpan jadi yatim DAN penanda di situs tidak lagi
     * menemukan fieldnya — `PageContentTest` mengejanya lengkap.
     *
     * ── Yang TIDAK ada di sini ──
     *
     * Susunan dan jumlah section: itu milik kode situs. Jumlah kartu di `lists`
     * juga tetap, karena desainnya menggambar sejumlah itu. Data yang punya
     * modul sendiri (milestone Heritage, berita, turnamen, …) tidak disalin ke
     * sini — section-nya membawa `elsewhere`, tautan ke layar yang mengelolanya.
     *
     * ── Batas karakter ──
     *
     * Ditentukan dari desain (lebar kolom, jumlah baris yang muat) dengan
     * naskah terbit sekarang sebagai acuan — keputusan 2026-09-29, belum ada
     * angka dari tim product. `lines` = satu baris per baris teks: `max` per
     * baris, `lines` [minimal, maksimal] barisnya.
     *
     * Kosong (atau tidak pernah diisi) = situs memakai naskah bawaan di kodenya.
     */
    'pages' => [
        'home' => [
            'label' => 'Home',
            'path' => '/',
            'sections' => [
                'hero' => [
                    'label' => 'Hero',
                    'fields' => [
                        'tagline' => ['type' => 'text', 'label' => 'Tagline', 'max' => 60],
                        'headline' => ['type' => 'text', 'label' => 'Headline', 'max' => 60],
                        'mission' => ['type' => 'textarea', 'label' => 'Mission', 'max' => 300],
                        'accountability' => ['type' => 'textarea', 'label' => 'Accountability line', 'max' => 200],
                        'primary_cta' => ['type' => 'text', 'label' => 'Main button — label', 'max' => 32],
                        'primary_cta_url' => ['type' => 'url', 'label' => 'Main button — link', 'max' => 300],
                        'secondary_cta' => ['type' => 'text', 'label' => 'Second button — label', 'max' => 32],
                        'secondary_cta_url' => ['type' => 'url', 'label' => 'Second button — link', 'max' => 300],
                    ],
                ],
                'countdown' => [
                    'label' => 'Upcoming Match Card',
                    'fields' => [
                        'cta' => ['type' => 'text', 'label' => 'Button — label', 'max' => 24],
                        'days' => ['type' => 'text', 'label' => 'Timer unit — days', 'max' => 12],
                        'hours' => ['type' => 'text', 'label' => 'Timer unit — hours', 'max' => 12],
                        'mins' => ['type' => 'text', 'label' => 'Timer unit — minutes', 'max' => 12],
                    ],
                    'elsewhere' => [['label' => 'The event, its date, place and link', 'href' => '/tournaments']],
                ],
                'feature' => [
                    'label' => 'Ready to Join (HQ picture)',
                    'fields' => [
                        'headline' => ['type' => 'lines', 'label' => 'Headline', 'max' => 40, 'lines' => [1, 3]],
                        'body' => ['type' => 'textarea', 'label' => 'Description', 'max' => 220],
                        'cta' => ['type' => 'text', 'label' => 'Button — label', 'max' => 28],
                    ],
                    'elsewhere' => [
                        ['label' => 'The federation in numbers (below)', 'href' => '/federations/stats'],
                        ['label' => 'The featured events band', 'href' => '/tournaments'],
                        ['label' => 'The news strip', 'href' => '/news'],
                    ],
                ],
                'partners' => [
                    'label' => 'Official Partners',
                    'fields' => [
                        'heading' => ['type' => 'text', 'label' => 'Heading', 'max' => 40],
                    ],
                    'elsewhere' => [
                        ['label' => 'The partner logos (the section stays hidden until one has a logo)', 'href' => '/blocks'],
                    ],
                ],
                'resources' => [
                    'label' => 'Resource Library',
                    'fields' => [
                        'heading' => ['type' => 'text', 'label' => 'Heading', 'max' => 32],
                        'intro' => ['type' => 'textarea', 'label' => 'Intro', 'max' => 160],
                    ],
                    'elsewhere' => [
                        ['label' => 'The documents', 'href' => '/documents'],
                        ['label' => 'Which documents show here', 'href' => '/documents/sections'],
                    ],
                ],
                'faq' => [
                    'label' => 'FAQ',
                    'fields' => [
                        'heading' => ['type' => 'text', 'label' => 'Heading', 'max' => 48],
                        'view_more' => ['type' => 'text', 'label' => '"View more" link — label', 'max' => 24],
                    ],
                    'elsewhere' => [['label' => 'The questions', 'href' => '/faq/pages']],
                ],
                'closing' => [
                    'label' => 'Closing Call (Join)',
                    'fields' => [
                        'headline' => ['type' => 'lines', 'label' => 'Headline', 'max' => 40, 'lines' => [1, 3]],
                        'body' => ['type' => 'textarea', 'label' => 'Description', 'max' => 300],
                        'cta' => ['type' => 'text', 'label' => 'Button — label', 'max' => 32],
                        'cta_url' => ['type' => 'url', 'label' => 'Button — link', 'max' => 300],
                    ],
                ],
            ],
        ],
        'about' => [
            'label' => 'About Us',
            'path' => '/about',
            'sections' => [
                'header' => [
                    'label' => 'Page Intro',
                    'fields' => [
                        'title' => ['type' => 'lines', 'label' => 'Title', 'max' => 40, 'lines' => [1, 3]],
                        'intro' => ['type' => 'textarea', 'label' => 'Description', 'max' => 320],
                    ],
                ],
                'overview' => [
                    'label' => 'Overview',
                    'fields' => [
                        'eyebrow' => ['type' => 'text', 'label' => 'Eyebrow', 'max' => 24],
                        'heading' => ['type' => 'text', 'label' => 'Title', 'max' => 60],
                    ],
                    'lists' => [
                        'cards' => [
                            'label' => 'Card',
                            'count' => 2,
                            'fields' => [
                                'title' => ['type' => 'text', 'label' => 'Title', 'max' => 30],
                                'body' => ['type' => 'textarea', 'label' => 'Description', 'max' => 180],
                            ],
                        ],
                    ],
                ],
                'heritage' => [
                    'label' => 'Our Journey',
                    'fields' => [
                        'eyebrow' => ['type' => 'text', 'label' => 'Eyebrow', 'max' => 24],
                        'heading' => ['type' => 'text', 'label' => 'Title', 'max' => 40],
                    ],
                    'elsewhere' => [
                        ['label' => 'Timeline milestones (year, title, photo)', 'href' => '/blocks/heritage'],
                    ],
                ],
                'vision' => [
                    'label' => 'Our Vision',
                    'fields' => [
                        'eyebrow' => ['type' => 'text', 'label' => 'Eyebrow', 'max' => 24],
                        'heading' => ['type' => 'lines', 'label' => 'Title', 'max' => 30, 'lines' => [1, 3]],
                        'lead' => ['type' => 'textarea', 'label' => 'Description 1', 'max' => 200],
                        'detail' => ['type' => 'textarea', 'label' => 'Description 2', 'max' => 220],
                    ],
                ],
                'pillars' => [
                    'label' => 'Why Dominoes? (Key Selling Point)',
                    'fields' => [
                        'heading' => ['type' => 'text', 'label' => 'Title', 'max' => 30],
                    ],
                    'lists' => [
                        'items' => [
                            'label' => 'Item',
                            'count' => 3,
                            'fields' => [
                                'title' => ['type' => 'text', 'label' => 'Title', 'max' => 34],
                                'body' => ['type' => 'textarea', 'label' => 'Description', 'max' => 150],
                            ],
                        ],
                    ],
                ],
                'mission' => [
                    'label' => 'Our Mission',
                    'fields' => [
                        'eyebrow' => ['type' => 'text', 'label' => 'Eyebrow', 'max' => 24],
                        'heading' => ['type' => 'lines', 'label' => 'Title', 'max' => 40, 'lines' => [1, 3]],
                        'intro' => ['type' => 'textarea', 'label' => 'Description', 'max' => 200],
                    ],
                    'lists' => [
                        'cards' => [
                            'label' => 'Card',
                            'count' => 4,
                            'fields' => [
                                'title' => ['type' => 'text', 'label' => 'Title', 'max' => 30],
                                'body' => ['type' => 'textarea', 'label' => 'Description', 'max' => 120],
                            ],
                        ],
                    ],
                ],
                'frameworks' => [
                    'label' => 'Our Global Network',
                    'fields' => [
                        'heading' => ['type' => 'text', 'label' => 'Title', 'max' => 40],
                        'intro' => ['type' => 'textarea', 'label' => 'Supporting text', 'max' => 200],
                        'apex_short' => ['type' => 'text', 'label' => 'Chart — top card, short name', 'max' => 8],
                        'apex_name' => ['type' => 'text', 'label' => 'Chart — top card, full name', 'max' => 30],
                        'federation' => ['type' => 'text', 'label' => 'Chart — middle cards, title', 'max' => 24],
                        'countries' => ['type' => 'lines', 'label' => 'Chart — middle cards, one country per line', 'max' => 24, 'lines' => [3, 3]],
                        'members' => ['type' => 'text', 'label' => 'Chart — bottom cards, title', 'max' => 24],
                        'members_detail' => ['type' => 'text', 'label' => 'Chart — bottom cards, subtitle', 'max' => 32],
                        'caption' => ['type' => 'lines', 'label' => 'Caption under the chart', 'max' => 80, 'lines' => [1, 2]],
                    ],
                ],
                'boards' => [
                    'label' => 'Executive Boards',
                    'fields' => [
                        'heading' => ['type' => 'text', 'label' => 'Title', 'max' => 30],
                        'intro' => ['type' => 'textarea', 'label' => 'Opening paragraph', 'max' => 260],
                        'members_label' => ['type' => 'text', 'label' => 'Members list — title', 'max' => 40],
                        'members' => ['type' => 'lines', 'label' => 'Members list — one name per line', 'max' => 40, 'lines' => [1, 12]],
                        'closing' => ['type' => 'textarea', 'label' => 'Closing paragraph', 'max' => 300],
                    ],
                    'lists' => [
                        'officers' => [
                            'label' => 'Officer',
                            'count' => 5,
                            'fields' => [
                                'role' => ['type' => 'text', 'label' => 'Position', 'max' => 30],
                                'name' => ['type' => 'text', 'label' => 'Name', 'max' => 40],
                            ],
                        ],
                    ],
                ],
                'headquarters' => [
                    'label' => 'Headquarters',
                    'fields' => [
                        'headline' => ['type' => 'text', 'label' => 'Title', 'max' => 60],
                        'hours' => ['type' => 'text', 'label' => 'Office hours', 'max' => 60],
                        // Alamat dan surel TIDAK di sini: situs sudah membacanya dari
                        // Contact & Social, dan dua tempat untuk satu nilai berarti
                        // yang satu diam-diam kalah. Telepon belum punya field di sana.
                        'phone' => ['type' => 'text', 'label' => 'Phone', 'max' => 30],
                    ],
                    'elsewhere' => [
                        ['label' => 'Address and email (shared with the site footer)', 'href' => '/contact-social'],
                    ],
                ],
            ],
        ],
        'domino' => [
            'label' => 'The Domino',
            'path' => '/domino',
            'sections' => [
                'header' => [
                    'label' => 'Page Intro',
                    'fields' => [
                        'title' => ['type' => 'lines', 'label' => 'Title', 'max' => 40, 'lines' => [1, 3]],
                        'subtitle' => ['type' => 'text', 'label' => 'Subtitle', 'max' => 60],
                        'intro' => ['type' => 'textarea', 'label' => 'Description', 'max' => 450],
                    ],
                ],
                'formats' => [
                    'label' => 'Singles & Doubles Formats',
                    'lists' => [
                        'panels' => ['label' => 'Format panel', 'count' => 2, 'fields' => [
                            'eyebrow' => ['type' => 'text', 'label' => 'Eyebrow', 'max' => 24],
                            'heading' => ['type' => 'text', 'label' => 'Title', 'max' => 40],
                            'body' => ['type' => 'textarea', 'label' => 'Description', 'max' => 280],
                            'players_label' => ['type' => 'text', 'label' => 'Stat 1 — label', 'max' => 24],
                            'players_value' => ['type' => 'text', 'label' => 'Stat 1 — value', 'max' => 24],
                            'hand_size_label' => ['type' => 'text', 'label' => 'Stat 2 — label', 'max' => 24],
                            'hand_size_value' => ['type' => 'text', 'label' => 'Stat 2 — value', 'max' => 24],
                        ]],
                    ],
                ],
                'rulebook' => [
                    'label' => 'The Rulebook',
                    'fields' => [
                        'heading' => ['type' => 'text', 'label' => 'Title', 'max' => 40],
                    ],
                    'lists' => [
                        'sets' => ['label' => 'Rule set (tab)', 'count' => 3, 'fields' => [
                            'tab' => ['type' => 'text', 'label' => 'Tab label', 'max' => 24],
                            'title' => ['type' => 'text', 'label' => 'Title', 'max' => 50],
                            'body' => ['type' => 'textarea', 'label' => 'Description', 'max' => 340],
                            'quote' => ['type' => 'textarea', 'label' => 'Quoted rule', 'max' => 180],
                            'cite' => ['type' => 'text', 'label' => 'Rule reference', 'max' => 30],
                        ]],
                    ],
                ],
                'regulations' => [
                    'label' => 'Referee Guidelines & Downloads',
                    'fields' => [
                        'rulebook_blurb' => ['type' => 'textarea', 'label' => 'Rulebook card — description', 'max' => 160],
                        'heading' => ['type' => 'text', 'label' => 'Title', 'max' => 40],
                        'intro' => ['type' => 'textarea', 'label' => 'Description', 'max' => 200],
                    ],
                    'lists' => [
                        'duties' => ['label' => 'Referee duty', 'count' => 4, 'fields' => [
                            'text' => ['type' => 'text', 'label' => 'Duty', 'max' => 100],
                        ]],
                    ],
                    'elsewhere' => [
                        ['label' => 'Which rulebook and which regulation buttons show here', 'href' => '/documents/sections'],
                        ['label' => 'Rulebook and competition regulation files', 'href' => '/documents'],
                    ],
                ],
                'faq' => [
                    'label' => 'FAQ',
                    'fields' => [
                        'heading' => ['type' => 'text', 'label' => 'Title', 'max' => 50],
                        'view_more' => ['type' => 'text', 'label' => 'Button label', 'max' => 24],
                    ],
                    'elsewhere' => [
                        ['label' => 'The questions and their order', 'href' => '/faq/pages'],
                    ],
                ],
            ],
        ],
        'tournaments' => [
            'label' => 'Tournaments',
            'path' => '/tournaments',
            'sections' => [
                'hero' => [
                    'label' => 'Highlighted Tournament',
                    'fields' => [
                        'watermark' => ['type' => 'text', 'label' => 'Background wordmark', 'max' => 24],
                        'watch_live' => ['type' => 'text', 'label' => 'Live stream button', 'max' => 28],
                    ],
                    'elsewhere' => [['label' => 'The highlighted tournament itself', 'href' => '/tournaments']],
                ],
                'rail' => [
                    'label' => 'All Tournaments',
                    'fields' => [
                        'heading' => ['type' => 'text', 'label' => 'Title', 'max' => 40],
                        'view_all' => ['type' => 'text', 'label' => 'View all button', 'max' => 24],
                    ],
                    'elsewhere' => [['label' => 'The tournaments themselves', 'href' => '/tournaments']],
                ],
                'regulations' => [
                    'label' => 'Tournament Regulations',
                    'fields' => [
                        'heading' => ['type' => 'text', 'label' => 'Title', 'max' => 48],
                    ],
                    'elsewhere' => [
                        ['label' => 'The regulation documents', 'href' => '/documents'],
                        ['label' => 'Which documents this shelf shows', 'href' => '/documents/sections'],
                    ],
                ],
                'champions' => [
                    'label' => 'Champions Hall',
                    'fields' => [
                        'heading' => ['type' => 'text', 'label' => 'Title', 'max' => 40],
                    ],
                    'elsewhere' => [['label' => 'The champions', 'href' => '/results/champions']],
                ],
                'results' => [
                    'label' => 'Olympic Results',
                    'fields' => [
                        'heading' => ['type' => 'text', 'label' => 'Title', 'max' => 40],
                        'col_year' => ['type' => 'text', 'label' => 'Column — year', 'max' => 24],
                        'col_event' => ['type' => 'text', 'label' => 'Column — event', 'max' => 24],
                        'col_category' => ['type' => 'text', 'label' => 'Column — category', 'max' => 24],
                        'col_winners' => ['type' => 'text', 'label' => 'Column — winners', 'max' => 24],
                        'col_federation' => ['type' => 'text', 'label' => 'Column — country / federation', 'max' => 32],
                        'more' => ['type' => 'text', 'label' => 'More results button', 'max' => 32],
                    ],
                    'elsewhere' => [['label' => 'The Olympic results', 'href' => '/results/olympic']],
                ],
                'faq' => [
                    'label' => 'Frequently Asked Questions',
                    'fields' => [
                        'heading' => ['type' => 'text', 'label' => 'Title', 'max' => 48],
                        'view_more' => ['type' => 'text', 'label' => 'View more button', 'max' => 24],
                    ],
                    'elsewhere' => [['label' => 'The questions and their order', 'href' => '/faq/pages']],
                ],
            ],
        ],
        'federation-members' => [
            'label' => 'Federation Members',
            'path' => '/federation-members',
            'sections' => [
                'hero' => [
                    'label' => 'Page Intro',
                    'fields' => [
                        'title' => ['type' => 'text', 'label' => 'Title', 'max' => 40],
                        'intro' => ['type' => 'textarea', 'label' => 'Description', 'max' => 240],
                        'cta' => ['type' => 'text', 'label' => 'Button label', 'max' => 28],
                    ],
                    'elsewhere' => [['label' => 'The membership figures', 'href' => '/federations/stats']],
                ],
                'map' => [
                    'label' => 'Member Map',
                    'fields' => [
                        'show_all' => ['type' => 'text', 'label' => '"Show All" filter', 'max' => 24],
                    ],
                    'lists' => [
                        'tiers' => ['label' => 'Membership tier', 'count' => 4, 'fields' => [
                            'label' => ['type' => 'text', 'label' => 'Tier name', 'max' => 28],
                        ]],
                    ],
                ],
                'directory' => [
                    'label' => 'Federation Directory',
                    'fields' => [
                        'heading' => ['type' => 'text', 'label' => 'Title', 'max' => 48],
                        'president_label' => ['type' => 'text', 'label' => 'Detail card — "President" label', 'max' => 24],
                        'headquarters_label' => ['type' => 'text', 'label' => 'Detail card — "Headquarters" label', 'max' => 24],
                        'contact_label' => ['type' => 'text', 'label' => 'Detail card — "Contact" label', 'max' => 24],
                    ],
                    'elsewhere' => [['label' => 'The member federations', 'href' => '/federations']],
                ],
                'benefits' => [
                    'label' => 'Membership Benefits',
                    'fields' => [
                        'heading' => ['type' => 'text', 'label' => 'Title', 'max' => 40],
                    ],
                    'lists' => [
                        'cards' => ['label' => 'Card', 'count' => 3, 'fields' => [
                            'title' => ['type' => 'text', 'label' => 'Title', 'max' => 36],
                            'body' => ['type' => 'textarea', 'label' => 'Description', 'max' => 180],
                        ]],
                    ],
                ],
                'process' => [
                    'label' => 'Application Process',
                    'fields' => [
                        'heading' => ['type' => 'text', 'label' => 'Title', 'max' => 40],
                        'intro' => ['type' => 'textarea', 'label' => 'Description', 'max' => 160],
                    ],
                    'lists' => [
                        'steps' => ['label' => 'Step', 'count' => 4, 'fields' => [
                            'title' => ['type' => 'text', 'label' => 'Title', 'max' => 24],
                            'body' => ['type' => 'textarea', 'label' => 'Description', 'max' => 140],
                        ]],
                    ],
                ],
                'cta' => [
                    'label' => 'Closing Call',
                    'fields' => [
                        'heading' => ['type' => 'text', 'label' => 'Title', 'max' => 72],
                        'intro' => ['type' => 'textarea', 'label' => 'Description', 'max' => 220],
                        'button' => ['type' => 'text', 'label' => 'Button label', 'max' => 24],
                    ],
                ],
            ],
        ],
        'player-membership' => [
            'label' => 'Player Membership',
            'path' => '/player-membership',
            'sections' => [
                'hero' => [
                    'label' => 'Page Intro',
                    'fields' => [
                        'title' => ['type' => 'text', 'label' => 'Title', 'max' => 48],
                        'body' => ['type' => 'textarea', 'label' => 'Description', 'max' => 260],
                        'cta' => ['type' => 'text', 'label' => 'Button label', 'max' => 32],
                    ],
                ],
                'what_is' => [
                    'label' => 'What is DWF ID?',
                    'fields' => [
                        'heading' => ['type' => 'text', 'label' => 'Title', 'max' => 40],
                        'lead' => ['type' => 'text', 'label' => 'Lead line', 'max' => 60],
                        'body' => ['type' => 'lines', 'label' => 'Paragraphs — one per line', 'max' => 360, 'lines' => [1, 4]],
                    ],
                ],
                'benefits' => [
                    'label' => 'Membership Benefits',
                    'fields' => [
                        'heading' => ['type' => 'text', 'label' => 'Title', 'max' => 40],
                    ],
                    'lists' => [
                        'cards' => ['label' => 'Benefit', 'count' => 6, 'fields' => [
                            'title' => ['type' => 'text', 'label' => 'Title', 'max' => 40],
                            'body' => ['type' => 'textarea', 'label' => 'Description', 'max' => 150],
                        ]],
                    ],
                ],
                'apply' => [
                    'label' => 'Who Can Apply & Application Process',
                    'fields' => [
                        'eligibility_heading' => ['type' => 'text', 'label' => 'Eligibility — title', 'max' => 40],
                        'eligibility_intro' => ['type' => 'textarea', 'label' => 'Eligibility — intro', 'max' => 160],
                        'requirements' => ['type' => 'lines', 'label' => 'Requirements — one per line', 'max' => 100, 'lines' => [1, 8]],
                        'process_heading' => ['type' => 'text', 'label' => 'Process — title', 'max' => 40],
                        'process_intro' => ['type' => 'textarea', 'label' => 'Process — intro', 'max' => 300],
                    ],
                    'lists' => [
                        'steps' => ['label' => 'Step', 'count' => 4, 'fields' => [
                            'title' => ['type' => 'text', 'label' => 'Title', 'max' => 36],
                            'body' => ['type' => 'textarea', 'label' => 'Description', 'max' => 130],
                        ]],
                    ],
                ],
                'cta' => [
                    'label' => 'Closing Call',
                    'fields' => [
                        'headline' => ['type' => 'lines', 'label' => 'Headline — one line each', 'max' => 40, 'lines' => [1, 3]],
                        'body' => ['type' => 'textarea', 'label' => 'Description', 'max' => 220],
                        // Tanpa tombol sejak 2026-10-02 — "Contact us" dicabut.
                    ],
                ],
            ],
        ],
        'development' => [
            'label' => 'Development',
            'path' => '/development',
            'sections' => [
                'header' => [
                    'label' => 'Page Intro',
                    'fields' => [
                        'title' => ['type' => 'lines', 'label' => 'Title', 'max' => 40, 'lines' => [1, 3]],
                        'intro' => ['type' => 'textarea', 'label' => 'Description', 'max' => 240],
                    ],
                ],
                'youth' => [
                    'label' => 'Youth Development',
                    'fields' => [
                        'eyebrow' => ['type' => 'text', 'label' => 'Eyebrow', 'max' => 24],
                        'heading' => ['type' => 'text', 'label' => 'Title', 'max' => 40],
                        'intro' => ['type' => 'textarea', 'label' => 'Description', 'max' => 200],
                        'download_cta' => ['type' => 'text', 'label' => 'Curriculum button label', 'max' => 36],
                    ],
                    'lists' => [
                        'stats' => ['label' => 'Figure', 'count' => 2, 'fields' => [
                            'figure' => ['type' => 'text', 'label' => 'Figure', 'max' => 24],
                            'label' => ['type' => 'text', 'label' => 'Label', 'max' => 32],
                        ]],
                    ],
                    'elsewhere' => [
                        ['label' => 'Which curriculum PDF the button downloads', 'href' => '/documents/sections'],
                    ],
                ],
                'certifications' => [
                    'label' => 'Official Certifications',
                    'fields' => [
                        'eyebrow' => ['type' => 'text', 'label' => 'Eyebrow', 'max' => 32],
                        'heading' => ['type' => 'text', 'label' => 'Title', 'max' => 40],
                        'areas_label' => ['type' => 'text', 'label' => 'Left column subtitle', 'max' => 40],
                        'priorities_label' => ['type' => 'text', 'label' => 'Right column subtitle', 'max' => 40],
                    ],
                    // Dua daftar bernomor yang tidak bisa diklik sejak 2026-10-08.
                    // Kiri dulunya tiga tab grade (C, B, A) yang menukar daftar
                    // kanan; kanan kini selalu `c_levels`. Kuncinya dipertahankan
                    // supaya naskah yang sudah tayang tidak hilang. Nomor kiri
                    // diturunkan dari urutan, jadi bukan field.
                    'lists' => [
                        'grades' => ['label' => 'Development area', 'count' => 3, 'fields' => [
                            'name' => ['type' => 'text', 'label' => 'Name', 'max' => 40],
                            'scope' => ['type' => 'text', 'label' => 'Scope', 'max' => 60],
                        ]],
                        'c_levels' => ['label' => 'Learning priority', 'count' => 3, 'fields' => [
                            'marker' => ['type' => 'text', 'label' => 'Marker', 'max' => 24],
                            'title' => ['type' => 'text', 'label' => 'Title', 'max' => 36],
                            'body' => ['type' => 'textarea', 'label' => 'Description', 'max' => 160],
                        ]],
                    ],
                ],
                'library' => [
                    'label' => 'Educational Resources',
                    'fields' => [
                        'eyebrow' => ['type' => 'text', 'label' => 'Eyebrow', 'max' => 24],
                        'heading' => ['type' => 'text', 'label' => 'Title', 'max' => 40],
                    ],
                    'elsewhere' => [
                        ['label' => 'Which documents appear here', 'href' => '/documents/sections'],
                    ],
                ],
                'grassroots' => [
                    'label' => 'Grassroots Initiatives',
                    'fields' => [
                        'eyebrow' => ['type' => 'text', 'label' => 'Eyebrow', 'max' => 24],
                        'heading' => ['type' => 'text', 'label' => 'Title', 'max' => 40],
                        'view_all' => ['type' => 'text', 'label' => 'Button label', 'max' => 24],
                    ],
                    'lists' => [
                        'cards' => ['label' => 'Card', 'count' => 3, 'fields' => [
                            'title' => ['type' => 'text', 'label' => 'Title', 'max' => 36],
                            'body' => ['type' => 'textarea', 'label' => 'Description', 'max' => 200],
                        ]],
                    ],
                ],
                'support' => [
                    'label' => 'Federation Support Programs',
                    'fields' => [
                        'eyebrow' => ['type' => 'text', 'label' => 'Eyebrow', 'max' => 24],
                        'heading' => ['type' => 'text', 'label' => 'Title', 'max' => 48],
                        'intro' => ['type' => 'textarea', 'label' => 'Description', 'max' => 200],
                        'form_heading' => ['type' => 'text', 'label' => 'Form title', 'max' => 36],
                        'form_intro' => ['type' => 'textarea', 'label' => 'Form description', 'max' => 240],
                        'federation_label' => ['type' => 'text', 'label' => 'Federation field — label', 'max' => 32],
                        'email_label' => ['type' => 'text', 'label' => 'Email field — label', 'max' => 32],
                        'needs_label' => ['type' => 'text', 'label' => 'Request field — label', 'max' => 48],
                        'submit' => ['type' => 'text', 'label' => 'Submit button', 'max' => 32],
                    ],
                    'lists' => [
                        'benefits' => ['label' => 'Benefit', 'count' => 3, 'fields' => [
                            'text' => ['type' => 'text', 'label' => 'Text', 'max' => 48],
                        ]],
                    ],
                    'elsewhere' => [
                        ['label' => 'Applications sent from this form', 'href' => '/contact-messages'],
                    ],
                ],
                'cta' => [
                    'label' => 'Closing Call to Action',
                    'fields' => [
                        'heading' => ['type' => 'text', 'label' => 'Title', 'max' => 48],
                        'body' => ['type' => 'textarea', 'label' => 'Description', 'max' => 280],
                        'button' => ['type' => 'text', 'label' => 'Button label', 'max' => 24],
                    ],
                ],
            ],
        ],
        'governance' => [
            'label' => 'Governance',
            'path' => '/governance',
            'sections' => [
                'header' => [
                    'label' => 'Page Intro',
                    'fields' => [
                        'title' => ['type' => 'lines', 'label' => 'Title', 'max' => 40, 'lines' => [1, 3]],
                        'eyebrow' => ['type' => 'text', 'label' => 'Subtitle', 'max' => 40],
                        'intro' => ['type' => 'textarea', 'label' => 'Description', 'max' => 240],
                    ],
                ],
                'overview' => [
                    'label' => 'Overview',
                    'fields' => [
                        'eyebrow' => ['type' => 'text', 'label' => 'Eyebrow', 'max' => 24],
                        'heading' => ['type' => 'text', 'label' => 'Title', 'max' => 40],
                        'role_label' => ['type' => 'text', 'label' => 'Role — label', 'max' => 24],
                        'role' => ['type' => 'textarea', 'label' => 'Role — text', 'max' => 260],
                        'commitments_label' => ['type' => 'text', 'label' => 'Commitments — label', 'max' => 32],
                        'commitments' => ['type' => 'textarea', 'label' => 'Commitments — text', 'max' => 260],
                    ],
                ],
                'committees' => [
                    'label' => 'Institutional Commitments',
                    'fields' => [
                        'heading' => ['type' => 'text', 'label' => 'Title', 'max' => 40],
                    ],
                    'elsewhere' => [
                        ['label' => 'The commitment cards (name, icon and points)', 'href' => '/people/committees'],
                    ],
                ],
                'documents' => [
                    'label' => 'Governance Documents',
                    'fields' => [
                        'heading' => ['type' => 'text', 'label' => 'Title', 'max' => 40],
                        'intro' => ['type' => 'textarea', 'label' => 'Description', 'max' => 260],
                    ],
                    'elsewhere' => [
                        ['label' => 'The documents themselves', 'href' => '/documents'],
                        ['label' => 'Which documents this section shows', 'href' => '/documents/sections'],
                    ],
                ],
                'strategy' => [
                    'label' => 'Domino Agenda 2030 Strategic Plan',
                    'fields' => [
                        'heading' => ['type' => 'text', 'label' => 'Title', 'max' => 60],
                        'intro' => ['type' => 'textarea', 'label' => 'Description', 'max' => 460],
                    ],
                ],
            ],
        ],
        'integrity' => [
            'label' => 'Integrity',
            'path' => '/integrity',
            'sections' => [
                'header' => [
                    'label' => 'Page Intro',
                    'fields' => [
                        'title' => ['type' => 'lines', 'label' => 'Title', 'max' => 40, 'lines' => [1, 3]],
                        'eyebrow' => ['type' => 'text', 'label' => 'Subtitle', 'max' => 60],
                        'intro' => ['type' => 'textarea', 'label' => 'Description', 'max' => 280],
                    ],
                ],
                'principles' => [
                    'label' => 'Core Principles',
                    'fields' => [
                        'heading' => ['type' => 'text', 'label' => 'Title', 'max' => 40],
                    ],
                    'lists' => [
                        'items' => ['label' => 'Principle', 'count' => 4, 'fields' => [
                            'label' => ['type' => 'text', 'label' => 'Name', 'max' => 24],
                            'detail' => ['type' => 'textarea', 'label' => 'Description', 'max' => 160],
                        ]],
                    ],
                ],
                'ethics' => [
                    'label' => 'Our Standards of Conduct',
                    'fields' => [
                        'heading' => ['type' => 'text', 'label' => 'Title', 'max' => 48],
                    ],
                    'lists' => [
                        'clauses' => ['label' => 'Clause', 'count' => 3, 'fields' => [
                            'title' => ['type' => 'text', 'label' => 'Title', 'max' => 40],
                            'body' => ['type' => 'textarea', 'label' => 'Description', 'max' => 180],
                        ]],
                    ],
                ],
                'measures' => [
                    'label' => 'Protecting the Game',
                    'fields' => [
                        'heading' => ['type' => 'text', 'label' => 'Title', 'max' => 40],
                        'intro' => ['type' => 'textarea', 'label' => 'Description', 'max' => 240],
                    ],
                    'lists' => [
                        'cards' => ['label' => 'Card', 'count' => 4, 'fields' => [
                            'title' => ['type' => 'text', 'label' => 'Title', 'max' => 40],
                            'detail' => ['type' => 'textarea', 'label' => 'Description', 'max' => 120],
                        ]],
                    ],
                ],
                'flow' => [
                    'label' => 'Integrity in Action',
                    'fields' => [
                        'heading' => ['type' => 'text', 'label' => 'Title', 'max' => 40],
                        'intro' => ['type' => 'textarea', 'label' => 'Description', 'max' => 240],
                    ],
                    'lists' => [
                        'steps' => ['label' => 'Step', 'count' => 4, 'fields' => [
                            'title' => ['type' => 'text', 'label' => 'Title', 'max' => 32],
                            'detail' => ['type' => 'textarea', 'label' => 'Description', 'max' => 120],
                        ]],
                    ],
                ],
                'report' => [
                    'label' => 'Report an Integrity Issue',
                    'fields' => [
                        'heading' => ['type' => 'text', 'label' => 'Title', 'max' => 40],
                        'intro' => ['type' => 'textarea', 'label' => 'Description', 'max' => 400],
                        'form_heading' => ['type' => 'text', 'label' => 'Form title', 'max' => 36],
                        'type_label' => ['type' => 'text', 'label' => 'Concern type — label', 'max' => 32],
                        'type_placeholder' => ['type' => 'text', 'label' => 'Concern type — placeholder', 'max' => 48],
                        'description_label' => ['type' => 'text', 'label' => 'Description — label', 'max' => 24],
                        'description_placeholder' => ['type' => 'textarea', 'label' => 'Description — placeholder', 'max' => 140],
                        'submit' => ['type' => 'text', 'label' => 'Submit button', 'max' => 24],
                    ],
                    'elsewhere' => [
                        ['label' => 'Reports sent from this form', 'href' => '/integrity-reports'],
                    ],
                ],
            ],
        ],
    ],

    /*
     * Alamat situs publik — sumber iframe pratinjau di editor halaman.
     * Tanpa garis miring penutup.
     */
    'site_url' => rtrim((string) env('DWF_SITE_URL', 'http://localhost:3000'), '/'),

    // Halaman publik tempat FAQ bisa ditempelkan, beserta labelnya.
    'faq_pages' => [
        'home' => 'Home Page',
        'domino' => 'Domino Page',
        'tournament' => 'Tournament Page',
    ],
];
