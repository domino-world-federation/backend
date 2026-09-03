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

        // "Tournament Rules Format" — aturan main yang dipakai.
        'rules_formats' => ['Single 101', 'Double 101', 'Draw Domino', 'Block Domino', 'Mixed Format'],

        'participant_types' => ['Players', 'Pairs', 'Teams'],

        // Pil di sebelah kategori di kartu publik (`592:16886`).
        'attendance' => ['Offline', 'Online'],

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
     * Kategori dokumen untuk Press Releases.
     *
     * Daftar tetap, bukan tabel: wireframe tidak punya layar CRUD untuk
     * kategori dokumen, dan membuatkannya berarti mengarang menu yang tidak
     * diminta. Kalau nanti perlu dikelola sendiri, pindahkan ke tabel dan
     * jaga nilainya tetap sama supaya baris lama tidak yatim.
     */
    'document_categories' => [
        'Annual Report',
        'Media Release',
        'Regulation',
        'Tournament Toolkit',
        'Partnership',
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

        // "Maximum 2 MB" di layar Add News (`252:4480`).
        'image_max_kb' => 2048,

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
