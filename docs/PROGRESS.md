# Progress — DWF Backoffice

Status pengerjaan portal admin. Diperbarui setiap modul selesai.

Desain acuan: Figma **DWF Wireframe**, `fileKey` `Cwd12fOcsUXmyLF4q1yWVP`,
kanvas **Backoffice**.

---

## Sudah jadi

**Fondasi** (2026-08-27)

- Laravel 13 + Inertia 3 + Vue 3.5 + Tailwind v4, TypeScript strict, PostgreSQL.
- Token desain lengkap dari wireframe — [DESIGN-TOKENS.md](DESIGN-TOKENS.md).
- Autentikasi sesi: login, logout, throttle 5×/menit per email+IP, regenerasi
  sesi. Ditulis tangan, bukan Breeze/Fortify.
- Shell navigasi: sidebar 312px sesuai `252:3403` (grup collapsible, sub-item,
  judul grup, blok akun, overlay gradient), topbar 56px, toggle tema.
- Sistem desain: `AppButton`, `AppField`, `FormRow`, `CardSection`,
  `PageHeader`, `Breadcrumbs`, `AppToggle`, `AppCheckbox`, `AppRadio`,
  `DataTable`, `AppPagination`, `ConfirmDialog`.
- Dashboard + halaman placeholder untuk tiap tujuan sidebar.
- **Design System** (`/design-system`) — satu halaman berisi contoh hidup tiap
  komponen berikut kode yang bisa disalin, plus kerangka halaman dan route untuk
  memulai modul baru. Ini rujukan pertama sebelum menulis layar CMS.
- Bar loading global (Inertia, emas DWF) + komponen `Skeleton` / `SkeletonTable`
  siap pakai untuk prop yang ditunda dan muat ulang sebagian.
- **Dashboard** dengan grafik: empat kartu statistik + sparkline, grafik garis
  dua seri (Publikasi), grafik kolom (Pesan masuk 14 hari), meter kelengkapan
  landing page, dan daftar aktivitas. Filter rentang 30d/90d/12m memuat ulang
  hanya prop yang bergantung padanya. **Seluruh angkanya query sungguhan** —
  tidak ada jalur data karangan, dan ada tes yang gagal kalau ada yang
  mengembalikannya.
- **Modul CMS lengkap**, semuanya CRUD sungguhan di PostgreSQL:
  - **News** — kategori (list, tambah, ubah, hapus, plus layar "Manage
    Category" yang menyunting inline) dan artikel
    (list dengan filter status/kategori/cari, tambah/ubah dengan tiga gambar,
    editor teks kaya, slug otomatis, draft/schedule/posting).
  - **Newsletter** (`/newsletter`) — pelanggan yang mendaftar dari kaki situs
    publik. Tanpa layar tambah: alamat yang diketik admin adalah alamat yang
    pemiliknya tidak pernah meminta dikirimi apa pun. Berhenti berlangganan
    MENANDAI, menghapus benar-benar menghapus — dua tindakan berbeda.
  - **Integrity Reports** (`/integrity-reports`) — laporan anonim dari
    `/integrity`. Tanpa tombol balas dan tanpa kolom pengirim, karena tidak ada
    satu pun kolom identitas yang disimpan.
  - **Home Page** (`/home-page`) — naskah hero dan band ajakan penutup, satu-satunya
    bagian halaman depan yang tidak dimiliki modul lain. Menggantikan grup
    "Landing Page" berisi delapan submenu placeholder; disajikan `/api/v1/home`.
  - **FAQ** — kategori + pertanyaan, "Apply to Page" dengan kuota 3 per halaman,
    dan DUA layar urutan yang menjawab pertanyaan berbeda: "Manage Order"
    (`/faq/manage`) mengurutkan halaman FAQ lengkap, "FAQ per Halaman"
    (`/faq/pages`) memilih dan mengurutkan isi Home, Domino, dan Tournament —
    masing-masing dengan peringkatnya sendiri.
  - **People & Governance** (`/people`) — Dewan Eksekutif (daftar bergambar),
    Sub-Komite, dan Komite Tetap. Dua bentuk penyuntingan, dan pemilihannya
    bukan selera: daftar bergambar memakai formulir kartu, daftar teks disunting
    langsung di barisnya. `remit` diketik dipisah koma lalu dipecah jadi pil.
  - **Partners & Heritage** (`/blocks`) — strip logo beranda dan timeline
    sejarah `/about`. Logo partner WAJIB (slot tanpa logo adalah slot kosong);
    `website_url` opsional selama pertanyaan terbuka #6 di PRD situs publik
    belum terjawab.
  - **Results & Winners** (`/results`) — menu terpisah yang diminta desain Add
    Tournament sendiri (`596:11483`). Tiga layar: pemenang per turnamen (hanya
    yang SUDAH selesai), Champions Hall lintas tahun, dan tabel hasil Olympic.
    Potret pemenang bertahan lewat **id barisnya**, bukan path dari klien.
    Potret juara sengaja opsional — R16 di PRD situs publik, dan pitanya
    mengingatkan itu tepat sebelum orang mengunggah wajah seseorang.
  - **Federations & Members** (`/federations`) — direktori badan anggota yang
    mengisi `/federation-members` di situs publik, plus layar **Statistik**
    (`/federations/stats`) yang mengisi roda angka di beranda DAN blok
    keanggotaan — satu tabel, dua lingkup. Bentuknya mengikuti kontrak
    `MemberFederation` di `types.ts`: sebelas field, sembilan di antaranya
    opsional. Menutup lubang yang dibuat modul Admin Users: field "Federation
    Scope" akhirnya punya layar pengelolanya.
  - **Events & Tournaments** (`/tournaments`) — formulir `585:11241`, sepuluh
    section dalam satu halaman dengan **Sidebar Progress Step** (`585:11561`):
    tiap langkah menggambar cincin berisi pecahan section yang sudah terisi.
    Empat tabel — induk, ofisial, jadwal, dan pivot ke Documents. Dua kelompok
    berulang (ofisial, jadwal yang bisa diurutkan ulang), regulasi menautkan
    dokumen yang SUDAH ADA dan sudah terbit ("files are not re-uploaded here"),
    dan tujuh dropdown pilihannya dari `config('dwf.tournaments')`.
    `stage` (akan datang/berlangsung/selesai) dan `registration_state`
    (dibuka/nanti/masih menerima/tutup) DITURUNKAN dari tanggal, bukan kolom.
  - **Documents** (`/documents`) — unggah PDF (maks 10 MB), kategori dari
    config. Disamakan dengan `369:5236`: kolom Document Title | Visibility
    (pemilih di dalam sel) | Category | Published | Created | Last Modified |
    **View** (tautan ke berkasnya). Sakelar `is_active` dua keadaan diganti
    Visibility empat keadaan, dan formulirnya memakai "Publish Time: Now /
    Schedule" (`262:3449`) — tanpa tombol Save Draft, karena desainnya cuma
    punya Cancel dan Save.
  - **Gallery** — event baru/lama, aset gambar atau video. Daftarnya **tabel**
    sesuai `478:5884`: Image Info (thumbnail + nama + kategori) | Visibility
    (pemilih di dalam sel) | Published | Created | Last Modified, filter
    Visibility + Category, pencarian menjangkau keterangan DAN nama event.
    Dua kolom pelaku baru (`created_by_id`, `published_by_id`) diisi event
    model — penayang dicatat sekali, saat ia pertama kali benar-benar tayang.
  - **Legal Pages** — Privacy Policy & Terms, blok judul+deskripsi yang bisa
    ditambah/dihapus/dinonaktifkan.
  - **Contact & Social** — pengaturan kunci-nilai.
  - **Contact Messages** — daftar, detail, tandai terbaca otomatis, hapus.
- 367 tes PHPUnit (3.022 asersi) jalan di PostgreSQL, termasuk penjaga XSS untuk
  tiap masukan editor, penjaga urutan route `/news/categories`, dan penjaga
  kejujuran dashboard.
- **Seeder data contoh (`php artisan db:seed`) mengisi backoffice dengan ISI
  YANG SAMA dengan mock situs publik** — `FrontendContentSeeder`, sumbernya
  `../../landing-page-nuxt/app/lib/api/mock/index.ts`. Gunanya bukan sekadar
  "ada datanya": begitu `routes/api.php` dibuat, situs publik bisa ditukar dari
  mock ke API tanpa halamannya berubah tampilan, dan tiap selisih yang muncul
  berarti kontraknya yang meleset. Berkas gambar TIDAK ikut — path mock menunjuk
  aset di repo situs publik, dan menyalinnya ke sini menghasilkan tautan rusak
  yang terlihat seperti data benar.
- **User Management** — daftar pengguna, tambah/ubah/hapus, pilih peran, dan
  sakelar 2FA per pengguna.
- **Roles & Permissions** (`/roles`) — layar sendiri dengan matriks
  modul × aksi. Peran bisa dibuat, disunting, dan dihapus dari UI; `super-admin`
  terkunci karena ia melewati pemeriksaan lewat `Gate::before` dan tidak punya
  baris izin untuk disunting. Empat peran bawaan dengan 29 izin yang
  dibangkitkan dari `App\Support\Access`. Tiap route modul dijaga `can:`,
  sidebar menyaring dengan izin yang sama.
- **Admin Users** (`/users`) disamakan dengan `528:8821`: judul "Admin Users",
  tombol "Add Admin", filter Status/Role/MFA, dan kolom
  Admin (nama + email) | Role | MFA Status | Status | Created | **Last Login**.
  Kolom terakhir menggantikan "Last Modified" atas permintaan pemilik repo —
  untuk sebuah akun, pertanyaan yang berguna "kapan terakhir dipakai", bukan
  "siapa terakhir menyuntingnya". Ditambah kolom `users.is_active` (akun
  nonaktif tidak bisa login, jejaknya tetap utuh) dan `users.last_login_at`
  (diisi listener `Login`, jadi semua pintu masuk tercatat).
- **Alur undangan admin** (`529:9714`) — akun dibuat TANPA sandi lalu menerima
  tautan sekali pakai yang kedaluwarsa 72 jam; bisa dikirim ulang dan dicabut
  dari daftar. Yang tersimpan hash tokennya, bukan tokennya. Layar penerimanya
  `/invitation/{token}`, di luar `auth` maupun `guest`.
- **Audit Log** (`/activity-log`) disamakan dengan `528:11529`: judul "Audit
  Log", kolom Time | Actor | Event | Target | IP Address | **Result**, filter
  Result dan rentang tanggal, plus pencarian event/target/IP. "Result"
  diturunkan dari nama kejadian (`failed`, `lockout`, `access_denied` =
  Blocked), bukan kolom tersendiri. Penolakan daftar IP kini ikut masuk jejak
  audit, jadi baris "Blocked" punya sumber nyata.
- **Roles & Permissions** (`/roles`) disamakan dengan `528:9745`: tombol
  "Create Role", filter Type/Scope, dan kolom
  Role | Type | Admins | Scope | Permission Summary | Last Updated. Empat kolom
  baru di tabel `roles` (`type`, `scope`, `summary`, `updated_by_id`); peran
  bawaan ditandai `system` oleh `AccessSeeder` dan tidak bisa dihapus.
- **Federasi anggota** (`member_federations`) — tabel MINIMAL (nama, negara,
  aktif, urutan) yang lahir lebih awal dari jadwalnya karena field "Federation
  Scope" di `529:9693` membutuhkan daftar untuk dipilih. **Belum punya layar
  CMS**; isinya lewat seeder sampai modul Members fase D dibangun.
- **IP Whitelist** (`/ip-whitelist`) — daftar alamat yang boleh membuka
  backoffice, dari `527:7038`. Lingkupnya tiga: semua admin, satu peran, atau
  satu orang. Masa berlaku permanen atau sementara dengan tanggal berakhir yang
  ditegakkan **saat request**, bukan lewat scheduler. Dua hal yang membedakannya
  dari modul lain:
  - **Ia benar-benar ditegakkan.** `EnforceIpWhitelist` dipasang di grup `web`
    dan menjaga tiap sesi yang sudah login. Tabel kosong = tidak ada yang
    dibatasi, dan pengguna yang tidak disasar aturan mana pun juga tidak
    dibatasi — tanpa itu, migrasi pertama akan mengunci semua orang.
  - **Ada penjaga anti-kunci-diri-sendiri.** Menyimpan, mematikan, atau
    menghapus aturan yang membuat alamatmu sendiri tidak lagi tercakup ditolak
    dengan pesan validasi. Desain hanya menuliskannya sebagai peringatan;
    peringatan tidak menahan apa pun.

  Izinnya `ip-whitelist.*`, **terpisah dari `users.*`** dan tidak diberikan ke
  peran `admin` maupun `viewer` — hanya `super-admin`, sesuai catatan di
  `527:7870`.
- **Log aktivitas** — tiap `created`/`updated`/`deleted` di seluruh model
  tercatat beserta siapa pelakunya dan diff atributnya, **plus login, logout,
  percobaan gagal, dan penguncian**. Setiap entri membawa IP dan perangkat
  (user agent diringkas jadi "Chrome · macOS", aslinya tetap disimpan sebagai
  tooltip). Layar pembacanya bisa disaring per modul, kejadian, dan pelaku.
  Hanya bisa dibaca.
- **2FA TOTP (Google Authenticator)** — QR untuk pendaftaran, enam kotak kode,
  dan kode pemulihan sekali-lihat. Sakelar global `DWF_TWO_FACTOR` plus kolom
  `users.two_factor_enabled` per pengguna (yang nanti dikelola User Management).
  Break-glass: `php artisan dwf:2fa:reset {email}`.
- **reCAPTCHA v2 di halaman login** — opsional, menyala sendiri begitu kedua
  kunci `.env` terisi. Gagal-terbuka kalau Google tidak bisa dihubungi; yang
  menahan tebak-sandi tetap `RateLimiter` (5/menit per email+IP).
- **Dua bahasa: Inggris (bawaan) dan Indonesia.** Pengalihnya **dimatikan**
  (`DWF_LOCALE_SWITCHER=false`) atas permintaan — backoffice tampil satu bahasa.
  Mesinnya tetap utuh: 276 kunci di `lang/{en,id}/backoffice.php`, satu sumber
  untuk teks antarmuka **dan** pesan validasi (`laravel-lang` untuk berkas
  bawaan Laravel). Menyalakannya kembali cukup satu baris `.env`; tombolnya
  muncul di topbar dan pilihannya tersimpan di `users.locale`.

---

- **SEO & Social** (`/seo-social`) — judul, deskripsi, dan gambar bagikan tiap
  halaman publik, plus satu baris **bawaan situs** (`route = '*'`) yang dipakai
  halaman tanpa barisnya sendiri dan untuk tiap field yang dikosongkan. Menutup
  dua lubang sekaligus: ke-18 halaman menanam metanya di berkas Vue masing-masing
  (ganti satu kalimat = deploy), dan **tidak ada satu pun `og:image` di seluruh
  repo** — tiap tautan DWF yang dibagikan tampil tanpa gambar. Izinnya memakai
  `settings.*` yang sudah ada, bukan modul baru.
- **API publik (`/api/v1`)** — 24 endpoint baca yang dikonsumsi
  `../landing-page-nuxt`. Enam aturan lintas endpoint (PRD §5) dikodekan sekali
  di `App\Http\Resources\PublicResource` dan dikunci 48 tes. Endpoint TULIS
  (contact, newsletter, notify turnamen) belum ada.

## Belum dibangun

Modul CMS di kanvas Backoffice **sudah selesai semua**, dan sejak 2026-09-03
tidak ada lagi tujuan sidebar yang berujung halaman kosong — mesin placeholder
ikut dihapus (P66). Yang tersisa bukan modul, melainkan pekerjaan yang tercatat
di [Pekerjaan terbuka](#pekerjaan-terbuka) di bawah.

**API publik `/api/v1` sudah ada** — 25 endpoint baca dan 4 endpoint tulis,
selesai 2026-09-02 dan 2026-09-03. Yang belum: situs Nuxt masih membaca
`app/content/*.ts` untuk sebagian halaman, dan ketiga formulirnya belum menukar
handler-nya. Rinciannya di
[`../../docs/PROGRESS.md`](../../docs/PROGRESS.md) Fase B.

---

## Penyimpangan dari wireframe

| # | Apa | Kenapa |
|---|---|---|
| P1 | **Sidebar ditambah 6 item** — FAQ, Press Releases, Gallery, Contact & Social, Contact Messages, Legal Pages | Komponen master `252:3403` tidak punya entri untuk keenamnya, tapi layarnya ada di kanvas dan breadcrumb-nya menuntut tujuan itu (`339:4483` "FAQ › FAQ List › Add FAQ", `258:8095` "Site Settings › Legal Pages", `258:8211` "Site Settings › Contact & Social"). Tanpa tambahan ini, enam modul tidak punya pintu masuk. Beberapa frame juga menggambar sidebar yang **mengganti** "SEO & Social" jadi "Privacy Policy" / "Contact & Social" — itu artefak wireframe, bukan desain; yang diikuti komponen master |
| P2 | **Ikon dinormalkan ke Phosphor** | Wireframe mencampur empat set ikon. Rinciannya di [DESIGN-TOKENS.md §6](DESIGN-TOKENS.md) |
| P3 | **Mode gelap diturunkan** | Topbar punya ikon matahari, tapi wireframe hanya menggambar mode terang. Palet gelapnya belum diverifikasi — [DESIGN-TOKENS.md §5](DESIGN-TOKENS.md) |
| P4 | **Halaman login dikarang** | Kanvas Backoffice mulai dari halaman yang sudah masuk; tidak ada desain login. Dibuat seminimal mungkin dengan token yang sama |
| P5 | **Dashboard dikarang** | Sidebar punya menu "Dashboard", kanvas tidak punya frame-nya. Isinya disusun sendiri: 4 kartu statistik + sparkline, grafik garis Publikasi, grafik kolom Pesan masuk, meter kelengkapan landing page, daftar aktivitas. Warna serinya **sengaja bukan warna merek** — emas `#E1B762` dan navy `#001D6C` sudah diuji dan gagal pemeriksaan pita terang/kontras; rinciannya di komentar `resources/css/app.css` |
| P6 | **State fokus ditambahkan** | Wireframe tidak menggambar `:focus-visible` sama sekali. Tanpa itu backoffice tidak bisa dipakai dengan keyboard |
| P7 | **Menu "Design System" ditambahkan** di grup "Developer" | Alat kerja, bukan layar produk — tidak ada di wireframe. Ada karena 18 layar yang menyusul memakai komponen yang sama berulang-ulang. Kalau nanti dianggap tidak layak ikut ke produksi, bungkus route-nya dengan `if (! app()->isProduction())` di `routes/web.php` dan saring entri navigasinya di `Navigation::tree()` |
| P8 | **Layar yang tidak ada di Figma dibangun dari pola sekitarnya** | Kanvas Backoffice tidak punya frame untuk: daftar FAQ Category, daftar FAQ, daftar Press Releases, dan Terms & Conditions. Keempatnya dibangun mengikuti saudaranya yang ada — daftar kategori meniru `246:516`, daftar FAQ/Press Releases meniru `252:4398`, Terms meniru `258:8086` (kedua section itu memang templat yang sama di Figma). Kalau desainernya nanti menggambar yang berbeda, keempat layar ini yang perlu dicocokkan lebih dulu |
| P9 | **Kategori Press Releases dari `config/dwf.php`, bukan tabel** | Layar `262:3449` punya dropdown kategori tapi kanvasnya tidak punya layar CRUD untuk mengelolanya. Membuatkan satu berarti mengarang menu yang tidak diminta. Pindah ke tabel nanti tinggal menjaga nilainya tetap sama |
| ~~P10~~ | ~~**Gallery ditampilkan sebagai kisi, bukan tabel**~~ | **DIBATALKAN 2026-09-02.** Alasan lamanya — "yang dibandingkan orang di sini gambarnya" — ternyata salah membaca layarnya. Desainnya (`478:5884`) memang tabel, lengkap dengan tiga kolom pelaku yang tidak muat di kartu, dan pertanyaan yang dijawab layar ini bukan "mana yang paling bagus" melainkan "mana yang tayang, sejak kapan, oleh siapa". Thumbnail-nya tetap ada di dalam sel Image Info |
| P11 | **Editor teks kaya memakai tiptap** | Toolbar `341:4786` (paragraph, bold, italic, underline, strikethrough, highlight, dua jenis daftar) memetakan satu-satu ke StarterKit + Underline + Highlight. Alternatifnya `document.execCommand` — sudah usang, beda perilaku di tiap browser, dan HTML keluarannya berantakan |
| P12 | **Dwibahasa dibangun, pengalihnya dimatikan** | Seluruh label wireframe berbahasa Inggris, jadi `lang/en` jadi sumbernya dan itu pula bahasa bawaannya. Terjemahan Indonesia tetap lengkap dan teruji, tapi tombol pengalihnya disembunyikan atas permintaan — `DWF_LOCALE_SWITCHER=true` mengembalikannya |
| P13 | **Overlay gradient sidebar dibuang** | Figma `252:3402` menggambar gradient #101010 → #636363 dari y=80 ke bawah. Dengan sidebar setinggi viewport (bukan setinggi frame 956px seperti di Figma), ujung terangnya jatuh di bawah menu terakhir dan terbaca seperti panel kedua yang menempel. Diminta dibuang; latarnya sekarang #101010 rata. Nilai gradientnya masih dicatat sebagai token di `app.css` kalau suatu saat diminta kembali |
| P14 | **Jarak vertikal dirapatkan dari spesifikasi Figma** | Figma memakai gap 24px hampir di mana-mana (`252:2368`). Diminta lebih rapat: sidebar −13% tinggi total (item 40→36px, jarak grup 16→8px), area konten −36px per halaman (breadcrumb→judul 24→12, antar-blok 24→16, dalam kartu 24→20). Padding kartu 24px **tidak** diubah — itu yang memberi kartunya bentuk |
| P17 | **"Manage Category" bukan layar pengurutan** | Saya sempat membangunnya sebagai daftar seret-urutan. Yang digambar `433:6116` adalah sunting inline: tiap baris punya pensil dan tong sampah, baris baru muncul di ujung lewat "Add Other", dan tong sampahnya MATI selama kategorinya masih dipakai. Sudah dibangun ulang, dan endpoint `news.categories.reorder` yang tidak lagi punya layar ikut dihapus. FAQ tetap punya "Manage Order" — itu memang ada di `343:4961` |
| P16 | **Menu titik-tiga di-teleport ke `<body>`** | Pembungkus `DataTable` memakai `overflow-x-auto` untuk tabel lebar, dan menyetel `overflow-x` ke nilai selain `visible` memaksa `overflow-y` ikut jadi `auto` — jadi menu baris yang jatuh di bawah tepi tabel terpotong. Sekarang `Teleport` + `position: fixed` dengan posisi dihitung dari tombolnya, membalik ke atas kalau ruang di bawah kurang |
| P15 | **Kolom konten rata kiri, batas 1440px** | Figma menggambar kolom 1080px di area konten 1128px pada frame 1440px — gutter 24px. Di lebar itu "di tengah" dan "rata kiri" kebetulan sama; di layar lebih lebar keduanya berpisah dan `mx-auto` membuang kelebihan ruang jadi lubang kosong di kiri (322px di 1800px dengan sidebar ciut). Terburuknya: menciutkan sidebar untuk dapat ruang justru melebarkan lubang itu. Sekarang rata kiri — gutter kiri selalu 24px — dan batasnya dinaikkan ke 1440px supaya tabel News List yang tujuh kolom tidak memecah tiap sel tanggal jadi dua baris |
| P18 | **Gerak ditambahkan di seluruh backoffice (`motion-v`)** | Wireframe statis; tidak ada satu pun frame yang menyatakan bagaimana sesuatu masuk atau keluar. Ditambahkan atas permintaan: modal (pudar + skala), menu baris, tombol ikon, pil pagination yang meluncur (`layoutId`), baris tabel yang masuk berurutan dan keluar ke kiri saat dihapus, dan gulir yang dianimasikan. Semua nilainya dari `resources/js/motion.ts`; `prefers-reduced-motion` dihormati sekali di akar lewat `<MotionConfig reduced-motion="user">` |
| P19 | **Modal ditengahkan dengan cara yang tidak biasa** | `<dialog>` + `showModal()` seharusnya sudah di tengah, tapi preflight Tailwind menyetel `margin: 0` ke semua elemen dan menghapus `margin: auto` bawaan browser. Dialognya sekarang direntangkan selayar penuh dan dibuat transparan, isinya ditengahkan flex, dan scrim-nya digambar sendiri — `::backdrop` pseudo-element, jadi tidak bisa dianimasikan |
| P20 | **Penjaga "perubahan belum disimpan"** | Tidak ada di wireframe. Diminta: keluar dari halaman formulir lewat Cancel, sidebar, Back/Forward, atau menutup tab harus ditahan dulu. Tiga jalur berbeda karena teknologinya berbeda — `beforeunload` (dialog bawaan browser, kalimatnya tidak bisa diganti), event `before` Inertia, dan `popstate` yang harus terdaftar SEBELUM Inertia. Arah traversal Forward tidak bisa dibedakan dari Back oleh `popstate`; yang diulang selalu langkah mundur |
| P21 | **Skeleton diberi durasi minimal 420 ms** | Secara teknis ia sudah benar sejak awal dan tetap tidak pernah terlihat: navigasi lokal selesai dalam 14–21 ms. Sekarang ia juga menyala saat halamannya sendiri baru tiba (sidebar, Back, URL langsung), bukan cuma saat filter — dihitung dari `sinceNavigationStart()`, jadi permintaan yang memang lambat tidak ditambahi tunggu |
| P22 | **Gambar potret dibuang dari News** | Layar `252:4480` meminta tiga gambar (hero, potret, lanskap); diminta dua. Kolom `portrait_image_path` ikut dibuang lewat migrasi — kolom yang tidak bisa lagi diisi lewat antarmuka mana pun akan bertahan bertahun-tahun sebagai pertanyaan yang tidak ada yang berani menjawab |
| P23 | **Layar baca artikel ditambahkan** | Tidak ada di kanvas. Diminta: judul di daftar bisa diklik untuk melihat detailnya. Terpisah dari `edit` dengan sengaja — memeriksa isi tidak seharusnya dimulai dengan membuka sesuatu yang bisa diubah tanpa sadar |
| P24 | **Status keempat: `unpublished`** | Kolom Visibility di `252:4398` menggambar empat keadaan — Published, Scheduled, Edit Draft, Unpublished — sementara model hanya punya tiga. `unpublished` ditambahkan (kolomnya `string`, jadi tanpa migrasi) karena bedanya nyata: draft belum pernah dibaca siapa pun, artikel yang ditarik sudah punya URL yang dibagikan orang. Dropdown-nya menolak `scheduled` kalau belum ada jadwal di depan — pilihan yang pasti ditolak server tidak ditawarkan hidup-hidup |
| P25 | **`updated_by_id` ditambahkan ke News** | Kolom "Last Modified" di desain menyebut nama. `author_id` tidak bisa menjawabnya: penulis dan penyunting terakhir sering bukan orang yang sama, dan justru perbedaan itu yang dicari saat sesuatu berubah tanpa ada yang mengaku |
| P26 | **Kolom Category dibuang dari daftar News** | Desainnya tujuh kolom tanpa Category, dan Category sudah jadi filter di atas tabel. Delapan kolom di 1440px membuat judul beritanya terpotong jadi dua kata |
| P27 | **Add News disamakan dengan `252:4480`** | Baris "Created Date" dibuang (tidak ada di desain), Author digambar sebagai field MATI alih-alih paragraf (satu baris yang bentuknya berbeda dari tetangganya terbaca sebagai rusak, bukan terkunci), Image Landscape ikut wajib, dan jadwal dipecah jadi jam + tanggal. Tombolnya Save Draft · Cancel · **Publish** |
| P28 | **"News Content" tetap ditandai wajib** — desainnya tidak | Wireframe memberi `*` pada Image Hero, Image Landscape, Title, dan Category, tapi tidak pada News Content. Servernya menuntut `body` terisi, dan field wajib yang tidak ditandai adalah cacat yang lebih buruk daripada tanda yang terlewat di wireframe. Kalau memang artikel tanpa isi harus bisa diterbitkan, yang diubah aturannya di `NewsArticleRequest`, bukan tandanya |
| P29 | **Jadwal dua kontrol, tapi satu kolom database** | Desain menggambar dua dropdown (jam, tanggal). Yang dikirim tetap satu `published_at`; memecahnya juga di server berarti dua field yang bisa saling bertentangan (tanggal terisi, jam kosong) dan aturan validasi yang harus menjelaskan gabungannya. Kontrolnya `<input type="time">` dan `type="date"` sungguhan, bukan dropdown yang digambar mirip — pemilih bawaan sistem sudah tahu format lokal dan bisa dipakai dengan keyboard |
| P30 | **Gambar WebP saja, di seluruh modul** | Wireframe menyebut ".jpg .jpeg .png"; diminta WebP saja. Gambar berita dan galeri tampil di situs publik, dan WebP memangkas beratnya 25-35% pada mutu yang sama — satu format juga berarti satu jalur yang perlu diuji. Batas ukurannya ikut turun ke 2 MB, mengikuti label di `252:4480` |
| P31 | **Rasio ditegakkan, ukuran persis tidak** | "Recommended size" di desain dibaca sebagai rasio wajib + ukuran minimum, bukan ukuran mati. 3840×1600 memenuhi kotak hero sama baiknya dengan 1920×800 dan lebih tajam di layar retina; menolaknya berarti memaksa orang mengecilkan gambar yang sudah benar. Kalau memang harus persis, ganti `min_width`/`ratio` di `dwf.uploads.image_specs` jadi `width`/`height` |
| P32 | **"Image Landscape" jadi "Image"** | Diminta. Nama kolom dan field-nya tetap `landscape_image_path` / `landscape`: mengganti nama kolom, migrasi, request, controller, dan dua halaman demi satu label adalah kerja yang tidak menghasilkan apa pun yang bisa dilihat |
| P33 | **Editor teks bisa menyisipkan gambar** | Tidak ada di wireframe — toolbar `341:4786` hanya menggambar tujuh tombol format. Ditambahkan atas permintaan, WebP saja, lewat `/editor/images`. Endpoint-nya berdiri sendiri, bukan menempel di News: editor yang sama dipakai FAQ dan Legal Pages, dan gambar yang disisipkan di tengah tulisan tidak dimiliki satu baris database mana pun |
| P34 | **Daftar izin Purifier diperbaiki** | Bukan penyesuaian desain melainkan cacat: bawaan `mews/purifier` membuang `h2`, `h3`, `s`, dan `mark`, padahal keempatnya tombol yang memang ada di toolbar. Orang menekan "Heading 2", menyimpan, dan judulnya kembali jadi paragraf tanpa pesan apa pun. `config/purifier.php` sekarang dipublikasikan dan ada tesnya |
| P35 | **Deskripsi halaman hukum jadi TEKS BIASA** | `258:8086` menggambar textarea polos — tidak ada toolbar, ada gagang resize di sudutnya. Editor teks kaya diganti textarea, dan `Purifier::clean()` diganti `strip_tags()`: `Purifier` membungkus hasilnya dengan `<p>` (`AutoFormat.AutoParagraph`), dan pada muat berikutnya penulisnya menemukan tag itu terketik di dalam kotaknya sendiri. Baris baru dijaga; dirender dengan `whitespace-pre-line`, tidak pernah sebagai HTML |
| P36 | **"Last Modified · nama · waktu" di bawah judul** | Ada di desain, belum ada kolomnya. `legal_pages.updated_by_id` ditambahkan — halaman hukum tidak punya penulis (ia lahir dari seeder), jadi yang berguna justru siapa yang TERAKHIR menyentuhnya |
| P37 | **"Cancel" jadi "Discard Changes", dan artinya berubah** | Tombolnya tidak lagi berpindah halaman; ia mengembalikan formulir ke keadaan tersimpan. Itu memang yang dimaksud orang saat menekan tombol yang duduk di sebelah Save. Ia mati selama tidak ada yang berubah, dan sesudah ditekan penahan kepergian ikut lepas sendiri |
| P38 | **Seluruh daftar disamakan dengan News** | Diminta. Tiap daftar kini punya: identitas yang menaut ke layar baca, kolom "siapa + kapan", sakelar keadaan yang bekerja tanpa membuka formulir, dan ekspor CSV yang mengikuti filter aktif. Dua fondasi bersama menahannya tetap sama: trait `TracksEditor` (mengisi `updated_by_id` lewat event `saving` model, jadi SEMUA jalur simpan tercatat tanpa controller mengingatnya) dan `App\Support\Csv` (BOM UTF-8, streaming, nama bertanggal). `StandardListTest` gagal kalau salah satu janji itu hilang di modul mana pun |
| P39 | **Judul Press Release tidak lagi membuka PDF langsung** | Sebelumnya judul di daftar menaut ke berkasnya dan membuka tab baru — kejutan, dan tidak ada cara memeriksa keterangannya lebih dulu. Sekarang ia menuju layar baca; unduhannya di sana, di sebelah nama dan ukuran berkas yang menjelaskan apa yang akan terbuka |
| P40 | **Galeri dapat waktu tayang, `is_active` dibuang** | `478:6930` meminta "Publish Time: Now / Schedule" dan tombol Save Draft · Publish. Menyimpan `is_active` berdampingan dengan status berarti dua sakelar yang sama-sama menjawab "apakah ini terlihat" — dan baris yang aktif tapi masih draft yang tidak bisa dijelaskan siapa pun. Kolomnya diganti `status` + `published_at`, persis seperti News; datanya dipindahkan, tidak dibuang |
| P41 | **Add Gallery: baris "Tournament Name" DIPERTAHANKAN** | Desain membuang baris Event Name seluruhnya saat Tournament dipilih dan hanya menyisakan satu dropdown — artinya turnamen baru tidak akan pernah bisa dibuat dari layar ini. Barisnya tetap ada dan New/Existing tetap ditawarkan; yang mengikuti desain adalah kata-katanya (labelnya berubah jadi "Tournament Name") |
| P42 | **Add Gallery: baris "Caption" DIPERTAHANKAN** | Desain tidak punya field alt sama sekali. Tanpanya setiap gambar terkirim ke situs publik tanpa teks alternatif, dan daftar galeri kehilangan satu-satunya label per aset. Hint gambarnya juga menyimpang: desain menulis ".jpg .jpeg .png", aturannya sekarang WebP saja (P30) |
| P43 | **MFA tetap diatur PER AKUN** — catatan `528:9743` menulis "MFA is mandatory at login and is not configurable per account", tapi sakelar `users.two_factor_enabled` dipertahankan | Keputusan pemilik repo, 2026-09-02, setelah tiga pilihan diajukan. Konsekuensinya kalimat di layar ikut disesuaikan: `users.context_note` sekarang menyebut undangan dan mengatakan verifikasi dua langkah diatur per akun. Pita yang menjanjikan hal yang tidak ditegakkan lebih buruk daripada tidak ada pita |
| P44 | **Satu admin boleh punya BANYAK peran** — `529:9683` menggambar Role sebagai satu dropdown | Keputusan pemilik repo, 2026-09-02. Formulirnya tetap kotak centang dan kolom tabelnya mencetak semua peran yang dimiliki |
| P45 | **Field "Confirm Temporary Password" (`556:10675`) tidak dibangun** | Desainnya membantah dirinya sendiri: label itu menempel pada sebuah TOGGLE berlabel "Required", di dalam formulir yang panel "Invitation Flow"-nya (`529:9716`) menyatakan "No password is created in this form". Yang dibangun alur undangannya, sesuai pilihan pemilik repo; field itu tidak punya arti di dalamnya |
| P46 | **Nama peran desain tidak dipakai** — "Admin PB", "Tournament Admin", "Content Admin", "Support Viewer" | Peran yang ada di sistem ini `super-admin`, `admin`, `editor`, `viewer`, dibangkitkan dari `App\Support\Access`. Nama desain bisa dibuat kapan saja lewat layar Roles; menanamnya sebagai peran bawaan berarti dua sumber kebenaran untuk hal yang sama |
| P47 | **`member_federations` dibuat sebelum modul Members** | Field "Federation Scope" (`529:9693`) butuh daftar untuk dipilih. Tabelnya sengaja minimal (nama, negara, aktif, urutan) dan **belum punya layar CMS** — sisanya milik fase D di [PRD-API-PUBLIK.md](../../docs/PRD-API-PUBLIK.md) §6b |
| ~~P48~~ | ~~**Modul bernama "Documents" di layar, `press-releases` di route, tabel, dan izin**~~ | **SELESAI 2026-09-02.** Renamenya dituntaskan: tabel `press_releases` → `documents`, model `PressRelease` → `Document`, route `/press-releases` → `/documents`, izin `press-releases.*` → `documents.*`. Izinnya DIGANTI NAMA, bukan dibuat ulang — `role_has_permissions` menunjuk `id`, jadi membuat ulang akan mencabut akses tiap peran kustom tanpa satu pun galat. Jejak audit (`log_name`, `subject_type`) ikut dipindahkan. Berkas lama tetap di `storage/app/public/press-releases/`; path disimpan per baris, jadi keduanya tetap terbuka |
| P49 | **Ukuran berkas dicetak sebagai subteks judul** — desain menggambar satu teks saja di sel itu | Layar Add-nya sendiri bicara soal batas ukuran ("maximum 10 MB"), dan angka itu paling dicari tepat di sebelah tautan View |
| P50 | **Layar DAFTAR turnamen dikarang** | Node `585:11241` hanya menggambar Add Tournament. Daftarnya meminjam pola Documents (`369:5236`) dan Gallery (`478:5884`) — identitas, Visibility di dalam sel, lalu "siapa + kapan" — plus dua kolom yang khas turnamen: Stage dan Registration |
| P51 | **Isi tujuh dropdown Add Tournament dikarang** | Desain menggambar kotaknya tanpa menuliskan pilihannya. Daftarnya di `config('dwf.tournaments')`, alasan yang sama dengan `document_categories`: wireframe tidak punya layar CRUD untuk satu pun di antaranya |
| P52 | **Section "Tournament Overview" (`596:11291`) tidak dibangun terpisah** | Isinya field yang sama persis dengan Overview Content yang sudah digambar di dalam Basic Information (`596:11005`), dan stepper desain (`594:11539`) memang tidak memuatnya. Dibangun satu kali |
| P53 | **Titik peta dua kotak (Latitude, Longitude), bukan satu** | Desain menulis satu field "Latitude, Longitude or map pin". Satu kotak berarti mengurai teks bebas, dan yang gagal diurai berakhir sebagai pin di tengah laut |
| P54 | **Modul Federations & Members dikarang seluruhnya** | Kanvas Backoffice tidak menggambarnya sama sekali, tapi situs publik memintanya lewat `getMemberFederations` dan `getMembershipStats`, dan field "Federation Scope" di Add Admin (`529:9693`) sudah bergantung padanya. Bentuknya meminjam pola Documents dan Gallery |
| P55 | **Federasi TIDAK menyimpan lintang-bujur** | Naluri pertama untuk peta di `/federation-members` keliru: 57 markernya dibaca dari artwork `world-map-dots.svg` sebagai persentase kotak 1505×752, bukan koordinat geografis (lihat `app/content/members/map-markers.ts`). Kolom lat/lng akan jadi kolom yang tidak pernah dibaca |
| P56 | **Modul Results & Winners dikarang seluruhnya** | Kanvas Backoffice tidak menggambarnya, tapi desain Add Tournament secara eksplisit menjanjikannya sebagai menu terpisah (`596:11483`) dan situs publik memintanya lewat `getChampions` dan `getOlympicResults`. Tiga layar, bentuknya meminjam pola modul lain |
| P57 | **Champions Hall TIDAK diturunkan dari `tournament_winners`** | Rel juara di beranda memuat gelar yang turnamennya belum tentu pernah masuk CMS ini; memaksanya punya baris turnamen berarti mengarang turnamen demi satu kartu. Dua tabel terpisah, disengaja |
| P58 | **Modul People & Governance dan Partners & Heritage dikarang seluruhnya** | Kanvas Backoffice tidak menggambar satu pun, tapi situs publik memintanya lewat `getBoardMembers`, `getSubCommittees`, `getStandingCommittees`, `getPartners`, dan `getHeritageMilestones` |
| P59 | **Daftar `coverage` turnamen diselaraskan ke mock situs publik** — "Inter-continental", "Championship", "Regional qualifier" | Kolom itu tercetak sebagai baris kecil di atas nama turnamen di kartu publik; daftar yang berbeda berarti kartu itu menampilkan istilah yang tidak pernah dipakai backoffice |
| P60 | **Menu "Header & Navigation" dan "Footer" DIBUANG dari sidebar** — keduanya ada di komponen master `252:3403` | Keputusan pemilik repo 2026-09-03. Keduanya placeholder yang berujung halaman kosong, dan yang mereka kelola sudah punya rumahnya: struktur menu dan kolom tautan footer adalah RUTE situs itu sendiri (menyuntingnya lewat CMS membuka jalan menunjuk halaman yang tidak ada), sementara alamat, surel, dan tautan sosial di kaki halaman dikelola "Contact & Social" dan disajikan `/api/v1/settings`. Menu yang berujung halaman kosong mengajari orang bahwa sebagian sidebar memang tidak berfungsi |
| P61 | **Deskripsi blok halaman hukum naik dari teks polos jadi HTML dasar**, dan Cookie Policy jadi halaman ketiga | Permintaan pemilik repo 2026-09-03. Membalik keputusan lama (`strip_tags()`): alasannya dulu adalah textarea polos yang memperlihatkan `<p>` sisipan `AutoParagraph` sebagai teks terketik, dan alasan itu hilang begitu kotaknya jadi `RichTextEditor variant="basic"`. Toolbar dasar dan profil Purifier `legal` sengaja dijaga identik — tebal, miring, garis bawah, coret, daftar, tautan; tanpa judul, gambar, atau sorot |
| P62 | **Urutan FAQ jadi milik HALAMAN** — tabel `faq_placements` menggantikan kolom JSON `faqs.pages` | Laporan pemilik repo 2026-09-03. `faqs.position` cuma satu angka yang dipakai bersama layar daftar, "Manage Order", DAN `/api/v1/faqs?page=…`, jadi urutan Home dan Domino adalah urutan yang sama disaring: satu pertanyaan yang menempel di dua halaman punya satu peringkat untuk keduanya, dan menggesernya demi Home ikut menggeser Domino tanpa ada yang memberi tahu. Layar "FAQ per Halaman" (`/faq/pages`) dikarang — tidak ada di kanvas Backoffice, tapi kuota "maks 3 per halaman" di `341:4864` sudah menyiratkan halaman sebagai wadah, dan wadah tanpa urutannya sendiri adalah wadah setengah jadi. `faqs.position` tetap ada dengan arti yang menyempit: urutan di halaman FAQ lengkap |
| P63 | **`/api/v1/faqs` mengirim kategori tiap pertanyaan** | Halaman FAQ lengkap di situs publik menggambar tab kategorinya DARI pertanyaan yang ada (laci kosong tidak muncul, lihat `FaqCategoryTabs`). Tanpa field ini ia tidak punya cara mengisinya, dan itu ketahuan saat memperbaiki P62 |
| P64 | **"Apply to Page" di formulir FAQ jadi BACA-SAJA** (`341:4861`), dan pertanyaan nonaktif kini tetap memakai slotnya | Lanjutan P62, atas pertanyaan pemilik repo 2026-09-03. Sederet centang bisa menjawab "ikut atau tidak" tapi tidak "nomor berapa", jadi ia selalu menempel di ujung tanpa mengatakannya; dan sejak penempatan punya peringkatnya sendiri, "halaman mana" berhenti jadi sifat milik pertanyaan dan jadi sifat milik hubungannya. Formulir tetap MENAMPILKAN halamannya, dengan tautan ke layar yang bisa mengubahnya. Aturan lama "nonaktif tidak menghabiskan kuota" ikut dibalik: halamannya sekarang digambar utuh, dan daftar lima baris dengan penghitung "3 dari 3" adalah layar yang membantah dirinya sendiri |
| P65 | **Grup "Landing Page" (8 submenu) DIGANTI satu menu "Home Page"** — grupnya ada di komponen master `252:3403` | Keputusan pemilik repo 2026-09-03. Dicocokkan dengan halaman depan yang sebenarnya, LIMA submenu akan jadi pintu kedua ke modul yang datanya memang tinggal di sana (Stats & Metrics → Federations, Featured Event → Tournaments, News Section → News, Federation Strip → Partners & Heritage), "Overview" menduplikasi widget kelengkapan di Dashboard, dan "About / Mission" menunjuk section yang tidak ada di halaman itu. Yang benar-benar yatim cuma dua — naskah hero dan band ajakan penutup, keduanya sebelumnya tertulis keras di `content/home/hero.ts` dan `content/home/join.ts` — dan keduanya muat di satu layar, lengkap dengan kartu "Dikelola di tempat lain" yang menaut ke modul pemiliknya |
| P66 | **Seluruh mesin placeholder DIHAPUS** — `PlaceholderController`, `Pages/Placeholder.vue`, dan perulangan route-nya | Akibat langsung P65: setelah grup itu pergi, tidak ada satu pun tujuan sidebar yang `built: false`, jadi mesinnya jadi kode mati. `NavigationTest` dibalik arahnya: dulu ia memastikan menu yang belum dibangun PUNYA placeholder — yang artinya menambah menu kosong lolos — sekarang ia gagal kalau ada tujuan sidebar tanpa layar sungguhan, dan mengetuk ke-21 tujuan yang ada |
| P67 | **`site_settings` dapat kolom `group`** (`contact` \| `home`) | Naskah halaman depan tinggal di tabel yang sama dengan kontak dan tautan sosial — dua penyimpanan kunci-nilai berdampingan berarti dua tempat untuk mencari saat sebuah nilai tidak muncul di situs. Tanpa kolom itu `/api/v1/settings` akan mengirim headline hero ke footer yang cuma butuh alamat surel: bukan kebocoran, tapi kontrak yang berhenti berarti apa-apa. Ada tesnya |
| P68 | **Tambah/ubah SEO & Social pindah ke layarnya SENDIRI** — sebelumnya formulir yang terbuka di atas daftarnya | Permintaan pemilik repo 2026-09-03. Layar ini memang dikarang (tidak ada frame-nya di kanvas Backoffice), dan bentuk sebarisnya jadi satu-satunya modul yang berbeda dari News, FAQ, Documents, Gallery, dan Tournaments — semuanya memakai `create`/`edit` terpisah. Baris bawaan (`*`) memakai layar yang sama; yang membedakannya cuma field Route yang tidak digambar untuknya |
| P69 | **Modul Newsletter dan Integrity Reports dikarang seluruhnya** | Kanvas Backoffice tidak menggambar satu pun, tapi situs publik punya formulirnya: kotak langganan di kaki tiap halaman dan formulir laporan di `/integrity`. Keduanya meminjam bentuk Contact Messages. Yang membedakan Integrity Reports: TIDAK ada tombol balas, karena tidak ada yang bisa dibalas |
| P70 | **`integrity_reports` sengaja TANPA kolom identitas** — tanpa nama, email, maupun alamat IP | Halaman yang mengirimnya berjanji "identitas Anda dirahasiakan", dan alamat IP adalah identitas: menyimpannya membuat janji itu bergantung pada siapa yang kebetulan punya akses database, bukan pada bentuk datanya. Konsekuensinya diterima: laporan tidak bisa ditanya balik, dan penyalahgunaan hanya ditahan throttle. Ada tes yang gagal kalau kolom identitas muncul |
| P71 | **`contact_messages.subject` jadi nullable** | Kolomnya lahir `NOT NULL` karena modul ini dibangun dari layar `258:8271`, sebelum ada satu pun pengirim sungguhan — dan formulir situs publik tidak menanyakan subjek, ia menanyakan TOPIK. Tidak diisi otomatis dari topik: itu menyimpan duplikat yang terbaca seperti sesuatu yang diketik orangnya. Controller yang jatuh ke topik saat kosong |
| P72 | **`ContactMessage::TOPICS` ditambah "Tournament Support"** | Ada di formulir situs publik, tidak ada di layar CMS. Yang menentukan daftar ini adalah apa yang bisa dikirim orang, bukan apa yang digambar penyaringnya — tanpa baris itu setiap pesan bertopik tersebut ditolak 422, dan yang terlihat pengirimnya cuma formulir yang gagal tanpa sebab. Ejaannya juga disamakan: situs publik memakai sentence case, CMS title case |
| P73 | **Daftar "Notify me" turnamen TIDAK jadi menu sendiri** | Ia milik satu turnamen, bukan daftar yang berdiri sendiri. Jumlahnya muncul di menu baris di layar Tournaments, dan alamatnya cuma keluar lewat ekspor per turnamen yang dijaga `tournaments.update` (bukan `.view` — mengunduh daftar alamat orang adalah tindakan, bukan pembacaan) dan DICATAT di jejak audit |
| P74 | **Route tulis di delapan modul lama DIPINDAH ke izin tulis** | Sampai 2026-09-03 News, FAQ, Documents, Gallery, Legal Pages, Contact & Social, Contact Messages, dan sakelar Visibility Tournaments mendaftarkan seluruh route tulisnya di dalam grup ber-`can:{modul}.view` — jadi peran `viewer` bisa menghapus artikel berita. Kegagalannya diam sempurna: izinnya ada di database dan tercentang di layar Roles. `WritePermissionTest` (25 kasus) menyapu seluruh modul sekaligus supaya yang berikutnya tertangkap |
| P75 | **Indeks ditambahkan untuk 35 kolom kunci asing** | PostgreSQL tidak membuatnya sendiri, MySQL membuat. Yang terpengaruh setiap `with()`, `withCount()`, dan penyaring per relasi — plus `ON DELETE CASCADE`, yang harus memindai tabel anak untuk menemukan yang dihapus |
| P76 | **38 kunci bahasa dan grup `placeholder` DIBUANG** | Sisa layar yang sudah tidak ada (Placeholder) dan kunci yang tidak pernah dirujuk satu berkas pun. `LocalizationTest` membandingkan struktur en/id, jadi kunci mati lolos selamanya kalau tidak dicari sengaja |
| P77 | **`overview` turnamen ternyata TIDAK dibersihkan Purifier** | Ditemukan 2026-09-03 saat mendaftar kolom HTML untuk penyapu gambar. Ia ditulis lewat `RichTextEditor` dan tampil di halaman turnamen situs publik, tapi disimpan mentah — satu-satunya kolom editor di repo yang begitu, sementara News, FAQ, dan Legal Pages sudah punya tesnya sejak awal. Sudah diperbaiki, dan tesnya ditambahkan |
| P78 | **`RichTextEditor`, `ReorderList`, dan `SelectField` masuk `/design-system`** | Ketiganya komponen bersama yang tidak pernah ada contohnya di sana, melanggar aturan repo sendiri ("komponen yang tidak ada di halaman itu akan ditulis ulang oleh orang berikutnya") |
| P79 | **Berkas dokumen pindah ke disk PRIVAT**, keluar lewat `GET /media/documents/{id}` | Sebelumnya PDF dokumen tinggal di disk publik dan disajikan web server langsung lewat symlink — jadi mengubah sebuah dokumen jadi draft atau unpublished TIDAK menurunkan berkasnya; yang diatur sakelar Visibility cuma daftarnya. Nama berkas acak menahan tebakan, bukan tautan yang sudah beredar. Yang belum tayang membalas 404 (bukan 403: keberadaan dokumen yang belum dirilis kadang justru yang rahasia), sementara admin ber-izin tetap bisa memeriksanya. Bukan URL bertanda tangan — tanda tangan yang terbit saat dokumen masih tayang tetap sah setelah diturunkan |
| P80 | **`/faqs?page=` diganti `?placement=`** | `page` adalah nama universal untuk "halaman ke berapa", dan orang pertama yang menambahkan pagination di sini akan menabraknya. Yang dimaksud memang penempatan — tabelnya pun bernama `faq_placements`. Gratis diganti: `getFaqs()` di situs publik belum ditulis |
| P81 | **`/settings` ikut camelCase** | Ia satu-satunya endpoint yang kuncinya diambil apa adanya dari kolom database (`primary_email`), sementara seluruh API memakai `publishedAt`, `fileUrl`, dst. Satu response yang beda gayanya memaksa pemakainya mengingat pengecualian |
| P82 | **Satu bentuk galat untuk seluruh `api/*`** | Bawaan Laravel membalas `No query results for model [App\Models\NewsArticle]` — BAHKAN dengan `APP_DEBUG=false` — jadi ia memberi tahu nama kelas, namespace, dan bahwa ini Laravel dengan route model binding. Dibatasi `api/*`: backoffice tetap memakai halaman galat Inertia-nya |
| P83 | **`?limit=` dijepit di satu tempat** | Tiga endpoint menuliskannya sendiri dengan hasil berbeda-beda, dan dua di antaranya memakai `min((int) $x, 48)` yang MELOLOSKAN nol dan angka negatif — `limit(0)` mengembalikan daftar kosong, yang terbaca seperti "tidak ada isinya" padahal permintaannya yang salah |
| P84 | **`/tournaments/highlighted` mengirim `ShowcaseEvent`, bukan `Tournament` penuh** | `Hero.vue` di situs publik menerima `ShowcaseEvent`, dan `getHighlightedTournament()` menyatakannya begitu. Bentuk berbeda untuk komponen yang sama adalah ketidakcocokan yang tidak akan ketahuan sampai halamannya dirender. Bentuknya sekarang dibangun satu method yang dipakai `showcase` DAN `highlighted` |
| P85 | **Potret anggota dewan dan gambar tonggak sejarah jadi WAJIB saat membuat** | `BoardCard` dan `MilestoneCard` menggambar `<NuxtImg :src>` tanpa penjagaan, dan `types.ts` menyatakan keduanya wajib — jadi baris tanpa gambar menghasilkan gambar rusak, bukan kartu tanpa gambar. Mengikuti alasan yang sudah dipakai logo partner. Tetap boleh kosong saat MENYUNTING |
| P86 | **Penyaring berdaftar tertutup menolak nilai asing dengan 422** | `?scope=member` (kurang satu huruf) diam-diam membalas statistik beranda. Berlaku untuk `scope`, `tier`, `registration`, `placement`; `category` dan `slug` tetap longgar karena isinya teks yang diketik orang |
| P87 | **Empat URL yang dipanggil `client.ts` diselaraskan ke skema API** — `/events/showcase`, `/events/featured`, `/committees/standing`, `/membership/stats` | Keempatnya ditulis sebelum API-nya ada dan tidak pernah cocok. Yang mengalah sisi frontend karena skema backend lebih konsisten: sumber dayanya turnamen, bukan "events"; `/standing-committees` sejajar dengan `/sub-committees` dan `/board-members`; dan statistik satu tabel dengan dua lingkup memang `?scope=` |
| P88 | **Letak media dan letak dokumen sama-sama bisa dipindah lewat `.env`** (`MEDIA_ROOT`, `MEDIA_URL`, `MEDIA_PRIVATE_ROOT`) | Menaruhnya di luar direktori aplikasi membuat deploy bergaya rilis-simbolik tidak menyentuhnya. Yang privat WAJIB di luar yang publik: kalau bertumpuk, symlink `public/storage` membuka setiap dokumen tanpa satu pun gejala — `AppServiceProvider` menolak boot kalau itu terjadi. Konsekuensi yang harus diingat: keduanya keluar dari cakupan backup git |
| P89 | **Batas ukuran gambar diturunkan 2 MB → 1 MB**, walau layar Add News (`252:4480`) menulis "Maximum 2 MB" | Situs publik memakai `provider: "none"` di `@nuxt/image` (CPU server produksi di bawah x86-64-v2, sharp menolak jalan), jadi TIDAK ADA yang mengecilkan gambar: byte yang diunggah adalah byte yang dikirim ke setiap pengunjung. Diukur dengan `cwebp` pada gambar seperti-foto — hero 1920×800 muat lapang (412 KB q82, 679 KB q95), yang ditolak 4K yang belum dikecilkan (1634 KB). Naikkan lagi begitu IPX hidup |
| P90 | **Deret logo partner jadi konten STATIS di situs publik; menunya dibuang dari sidebar** | Keputusan pemilik repo 2026-09-03: delapan logo yang berganti sekali semusim tidak sebanding dengan satu layar untuk mengelolanya. Menu jadi "Heritage" saja. Layar Partners, tabelnya, dan `/api/v1/partners` TIDAK dihapus — cuma tidak lagi ditaut, jadi mengembalikannya cukup satu baris di `Navigation`. Baris partner juga dibuang dari kartu "Dikelola di tempat lain" dan dari widget Dashboard: menautkannya ke `/blocks` akan menjanjikan bahwa menyuntingnya di sana mengubah halaman |
| P91 | **Berita disemai dari `MOCK_NEWS`, bukan dari judul karangan** | Sebelumnya `DatabaseSeeder` menanam tujuh judul karangan dengan empat kategori yang tidak ada satu pun di mock — akibatnya penyaring kategori di `/news/all` memperlihatkan daftar yang berbeda dari yang dirancang halamannya. Sekarang sebelas artikel dan enam kategori disalin apa adanya (slug ikut, karena slug adalah URL), plus dua baris tambahan (draft dan scheduled) supaya layar daftar backoffice memperlihatkan lebih dari satu keadaan Visibility |
| P92 | **`php artisan dwf:demo-images`** — pembuat gambar WebP contoh | Seeder sengaja tidak menanam berkas biner: repo tidak memuat satu pun foto, dan menaruhnya di git berarti megabyte yang ikut ter-clone selamanya. Tapi baris tanpa gambar juga bukan data contoh yang berguna — halaman berita menggambar hero tanpa penjagaan, jadi yang terlihat gambar rusak. Perintah ini jembatannya; gambarnya gradien berlabel "CONTOH" supaya tidak ada yang keliru menganggapnya foto sungguhan. Menolak jalan di produksi |
| P93 | **`FaqItem.answer` menerima DUA bentuk** — `FaqSegment[]` untuk naskah di repo, `string` HTML untuk yang dari CMS | Laporan pemilik repo 2026-09-03: urutan FAQ yang disetel di "FAQ per Halaman" tidak berpengaruh di beranda. Sebabnya ketiga section FAQ masih mengimpor `content/*` — API-nya tidak pernah dibaca. Menyambungkannya menuntut bentuk `answer` diselesaikan: potongan datar tidak bisa mewakili daftar berpoin dan tautan yang ada di toolbar editor, jadi HTML yang menang. Dibuat MENERIMA keduanya, bukan diganti, supaya tiga halaman statis (`/page/faq`, dan naskah domino/tournament sebagai mock) tidak perlu ditulis ulang. `v-html` dibolehkan hanya di satu baris itu, dengan alasannya, bukan dengan mematikan aturan lint-nya |
| P94 | **Halaman hukum disemai dari naskah situs publik, plus Cookie Policy yang ditulis baru** | Privacy (8 klausa) dan Terms (9) disalin kata per kata dari `content/privacy/sections.ts` dan `content/terms/sections.ts` supaya penukaran naskah statis → API tidak mengubah satu kalimat pun. Cookie Policy tidak ada di sana — naskahnya ditulis untuk menggambarkan apa yang benar-benar dilakukan aplikasi ini (cookie sesi dan token anti-pemalsuan, tanpa satu pun pelacak), bukan salinan kebijakan orang lain. **Ketiganya tetap harus dibaca penasihat hukum sebelum tayang.** Alamat surel yang dulu jadi field terpisah dilipat jadi tautan `mailto:` di dalam HTML-nya |
| P95 | **Nama halaman hukum dicari lewat kuncinya, bukan dipilih dengan `? :`** | Laporan pemilik repo 2026-09-04: layar Cookie Policy menyebut dirinya "Kebijakan Privasi". Datanya benar — yang salah `key === 'terms' ? terms : privacy` di `Legal/Index.vue` dan `Legal/Form.vue`, ditulis waktu halamannya masih dua. Halaman ketiga jatuh ke cabang `else`, dan namanya keliru sekaligus di judul, breadcrumb, judul kartu, dan label tiap blok — tanpa satu pun galat. Diganti pencarian `backoffice.legal.names.<kunci>`; halaman yang terjemahannya belum ada menampilkan kuncinya apa adanya, jelek tapi tidak bisa disangka halaman lain. `LegalPageNamesTest` mengunci dua bentuk kegagalannya: kunci yang tidak sama dengan `LegalPageController::TITLES`, dan dua halaman dengan nama identik |
| P96 | **`draft` dibuang dari status yang bisa disetel dari daftar; "Edit Draft" jadi TAUTAN ke formulirnya** | Permintaan pemilik repo 2026-09-04. Draft satu-satunya keadaan yang bukan tujuan melainkan akibat: ia lahir dari tombol "Save Draft" di dalam formulir, bersama isi yang baru diketik. Bisa dipilih dari daftar berarti ada jalan menarik tulisan tayang kembali jadi draft tanpa seorang pun membuka isinya — menarik dari peredaran tetap bisa, namanya `unpublished`. Ditegakkan di `QUICK_STATUSES` keempat modul, jadi permintaan rakitan tangan pun ditolak 422. Label `news.status_draft` ikut jadi "Draft"; "Edit Draft" pindah ke kunci sendiri, karena ia nama AKSI dan sempat terpakai sebagai nama status di empat dropdown penyaring. `QuickStatusTest` menyapu keempatnya |
| P97 | **Galeri turnamen menunjuk turnamennya, bukan mengetik namanya** | Permintaan yang sama. Layar Add Gallery dulu menawarkan New/Existing juga untuk Tournament, dan "New" di sana melahirkan turnamen kedua yang cuma ada di galeri — tanpa tanggal, tanpa venue, dan tidak ikut berubah saat yang asli diganti nama. Sekarang satu dropdown berisi SELURUH turnamen apa pun statusnya (galeri disiapkan sebelum turnamennya tayang; menyaring yang tayang saja membalik urutan kerjanya). `gallery_events.tournament_id` unik dan nullable — baris lama memang tidak punya jawabannya, dan menebaknya lewat kemiripan nama justru melahirkan tautan salah. Nama album disalin ulang dari turnamennya tiap simpan, slug-nya tidak: yang satu label, yang lain alamat publik. Tipe "Event" tetap dua jalan — acara galeri tidak punya modul sendiri, jadi membuang "New" di sana berarti tidak ada layar yang bisa melahirkannya |
| P98 | **Field acara diikat ke `type`, bukan cuma ke `event_mode`** | Ditemukan lewat tes yang mengirim persis muatan formulirnya. Layar memulai dengan `event_mode: 'new'` dan tidak membuangnya saat Tournament dipilih — field-nya cuma berhenti digambar. `required_if:event_mode,new` karena itu menuntut `event_name` yang tidak punya kotak di layar: yang terlihat tombol Publish yang tidak melakukan apa-apa, dengan galat menempel pada field tak terlihat. Diperbaiki di server, bukan dengan membersihkan keadaan formulir di Vue — aturan yang benar tetap benar untuk permintaan yang tidak datang dari layar itu |

---

## Pekerjaan terbuka

> **Server butuh satu entri cron.** `* * * * * cd /path/ke/app && php artisan
> schedule:run >> /dev/null 2>&1`. Tanpa itu `editor:prune` dan
> `activitylog:clean` tidak pernah jalan, dan tidak ada satu pun layar yang
> memperlihatkannya — yang terlihat cuma disk yang penuh, berbulan-bulan
> kemudian.

- [~] **Deploy: nginx sudah jalan, sisanya belum.** Dua host —
      `fed-bo.pborado.com` (backoffice) dan `fed-api.pborado.com` (**hanya
      `/api`**, sisanya 404). Berkas confignya **di luar repo**: `deploy/`
      di-gitignore karena isinya path dan hostname mesin sungguhan, jadi
      ALASAN dan kerangkanya yang disimpan, di
      [docs/PRODUCTION.md](PRODUCTION.md) §7 — cukup untuk menyusunnya ulang
      dari nol. Yang belum: unit systemd/php-fpm pool sendiri, dan skrip rilis.
- [ ] **Ikon lonceng di topbar mati.** Notifikasi belum punya sumber data;
      tombolnya `disabled`, digambar `cursor-not-allowed`, dan `title`-nya
      menyebut alasannya — bukan mengulang namanya.
- [ ] **Skeleton belum dipasang di pemuatan PERTAMA.** Sekarang ia hanya
      menyala saat filter, cari, dan pindah halaman. Memasangnya di pemuatan
      pertama butuh `Inertia::defer()`, dan itu menambah satu round-trip untuk
      tabel yang sekarang masih cepat. Pertimbangkan lagi begitu ada tabel yang
      benar-benar lambat.
- [ ] **Berkas seeder Press Releases tidak ikut ditanam.** `file_path`-nya
      menunjuk PDF yang belum ada, jadi tautan unduhnya akan 404 sampai
      dokumennya benar-benar diunggah lewat layarnya. Disengaja: menanam PDF
      palsu ke repo lebih buruk daripada tautan yang jelas kosong.
- [ ] **Halaman Design System belum diterjemahkan.** Ia dokumentasi internal
      untuk yang menulis kode, bukan layar produk — teksnya sengaja dibiarkan
      dalam satu bahasa supaya contoh kodenya tidak ikut berubah.
- [x] **Gambar editor disapu `editor:prune`**, dijadwalkan mingguan. Ia
      menelusuri keempat kolom HTML (`news_articles.body`, `faqs.answer`,
      `legal_page_blocks.description`, `tournaments.overview`) dan membuang
      berkas di `storage/app/public/editor` yang tidak disebut siapa pun DAN
      berusia lebih dari 7 hari. Ambang usianya wajib: gambar diunggah saat
      disisipkan, bukan saat disimpan.
- [x] **Log aktivitas dibersihkan `activitylog:clean`**, dijadwalkan harian.
      Batasnya `ACTIVITY_LOG_RETENTION_DAYS` (bawaan 365) — angka itu keputusan
      kebijakan, bukan kenyamanan, jadi ia terlihat di `config/activitylog.php`
      dan `.env.example`, bukan tersembunyi di dalam paket.
- [ ] **Layar kode pemulihan tidak ada di Figma.** Dibuat sendiri karena tanpa
      itu 2FA punya cacat yang hanya ketahuan pada hari terburuk: kehilangan
      ponsel berarti kehilangan akun, permanen.
- [ ] **Artikel terjadwal tidak dipindahkan proses apa pun.** Scope `live()`
      menghitungnya saat query (status `scheduled` + `published_at` sudah lewat
      = terbit), jadi situs publik tetap benar tanpa cron. Tapi kolom `status`
      di database tetap `scheduled` selamanya — kalau nanti ada laporan yang
      membaca kolom itu langsung, ia akan salah.
- [ ] **Avatar pengguna belum bisa diunggah.** Kolom `avatar_path` sudah ada dan
      sidebar sudah menampilkannya; yang belum ada layar untuk mengisinya —
      sementara ini sidebar menggambar inisial nama.
