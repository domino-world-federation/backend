# DWF Backoffice — aturan kerja

Portal admin Domino World Federation. Laravel 13 + Inertia + Vue 3, dan nanti
juga menampung API publik yang dikonsumsi [`../landing-page-nuxt`](../landing-page-nuxt).

Baca [README.md](README.md) untuk cara menjalankannya,
[docs/DESIGN-TOKENS.md](docs/DESIGN-TOKENS.md) sebelum menulis komponen,
[docs/PROGRESS.md](docs/PROGRESS.md) untuk status modul, dan
[docs/API.md](docs/API.md) sebelum menyentuh `routes/api.php`, dan
**[docs/PRODUCTION.md](docs/PRODUCTION.md) sebelum menyentuh server** — isinya
hal-hal yang gagal DIAM-DIAM di produksi: cron yang lupa dipasang, symlink
`storage` yang mati, `.env` yang kosong tanpa galat, dan perintah yang tidak
boleh dijalankan di database berisi.

Modul CMS (News, FAQ, Press Releases, Gallery, Legal Pages, Contact & Social,
Contact Messages) **sudah jadi**. Pakai salah satunya sebagai contoh saat
membangun modul berikutnya — `app/Http/Controllers/Cms/NewsArticleController.php`
plus `resources/js/Pages/News/` adalah yang paling lengkap.

**Sebelum menulis layar baru, buka `/design-system` di aplikasi yang jalan.**
Halaman itu memuat contoh hidup tiap komponen bersama berikut kode yang bisa
disalin — termasuk kerangka halaman dan cara mendaftarkan route-nya. Sumbernya
di [`resources/js/Pages/DesignSystem/`](resources/js/Pages/DesignSystem/).
Menambah komponen bersama berarti menambah contohnya di sana juga; komponen yang
tidak ada di halaman itu akan ditulis ulang oleh orang berikutnya.

## Hal yang mudah keliru

- **Selalu Bun, jangan npm/yarn/pnpm.** `bun install`, `bun add`, `bun run`.
  Commit `bun.lock`. Composer tetap composer. `bun.lock` juga yang membuat
  `artisan dev` memilih `bun run dev`, bukan `npm run dev` — kalau file itu
  hilang, Laravel diam-diam jatuh ke npm.
- **Menyalakan lingkungan dev: `composer dev`, satu perintah.** Ia memanggil
  `php artisan dev`, yang menjalankan `php artisan serve`, `bun run dev`, dan
  `php artisan pail` sekaligus sebagai tab. Jangan menyarankan orang membuka
  dua terminal. Isi tabnya disetel lewat `DevCommands` di
  `app/Providers/AppServiceProvider.php`.
- **Brand ditulis `DWF`**, bukan `DFW`. Nama folder induk (`dfw`) keliru dan
  dibiarkan agar path tidak putus — jangan pakai `dfw` untuk file baru.
- **Commit tanpa atribusi AI** — tanpa trailer `Co-Authored-By`, tanpa
  `Generated with`, tanpa emoji bot, di pesan commit maupun deskripsi PR.
- **Tidak ada lagi halaman placeholder.** `PlaceholderController` dan
  `Pages/Placeholder.vue` sudah dihapus: setiap tujuan sidebar punya layarnya
  sendiri, dan `NavigationTest` gagal kalau ada yang ditambahkan tanpa itu.
  Kalau butuh menu untuk modul yang belum ada, bangun layarnya dulu — menu yang
  berujung halaman kosong mengajari orang bahwa sebagian sidebar memang tidak
  berfungsi, dan sesudah itu yang berfungsi pun ikut tidak dicoba. Tiga
  kelompok terakhir yang memakainya (Header & Navigation, Footer, Landing Page)
  semuanya berakhir dibuang atau diganti layar sungguhan.
- **`site_settings` punya kolom `group`, dan `map()`/`putMany()` MENUNTUTnya.**
  `contact` dibaca footer lewat `/api/v1/settings`; `home` naskah halaman depan
  lewat `/api/v1/home`. Tanpa pemisahan itu endpoint pertama mengirim headline
  hero ke footer yang cuma butuh alamat surel. Jangan menambahkan tabel
  kunci-nilai kedua untuk kelompok berikutnya — tambahkan nilai `group` baru.
- **Struktur sidebar cuma punya satu sumber:
  [`app/Support/Navigation.php`](app/Support/Navigation.php).** Sidebar Vue,
  daftar route di `routes/web.php`, dan judul halaman placeholder semuanya
  membacanya dari sana. Menambah menu dengan menempelkan `<Link>` di
  `AppSidebar.vue` menghasilkan item yang 404 — dan `NavigationTest` memang
  ditulis untuk gagal kalau itu terjadi.
- **Jangan menulis nilai warna, ukuran font, atau spasi langsung di komponen.**
  Semuanya lewat token `@theme` di `resources/css/app.css` dan utility
  `text-heading-4`, `text-body-s`, `text-nav-l`, dst. Palet backoffice
  (CoolGray + Roboto) **berbeda** dari situs publik (Bebas Neue + Inter + emas)
  — jangan menyalin `../landing-page-nuxt/docs/DESIGN-TOKENS.md` ke sini.
- **`@fonts` wajib ada di `resources/views/app.blade.php`,** sebelum `@vite`.
  Tanpa baris itu, `@font-face` untuk Inter/Roboto/Plus Jakarta Sans tidak
  pernah dipasang: halaman tetap tampil, hanya diam-diam memakai font sistem —
  build lolos, typecheck lolos, dan yang salah cuma kelihatan kalau
  diperbandingkan dengan Figma.
- **Sidebar dan topbar dipatok (`sticky`), yang menggulir cuma isi halaman.**
  Sidebar `sticky top-0 h-screen`, daftar menunya `flex-1 min-h-0 overflow-y-auto`.
  Jangan memasang `overflow-hidden` di `AdminLayout` atau pembungkus di atasnya —
  satu saja cukup untuk mematikan `sticky` tanpa pesan error, dan gejalanya
  (sidebar hanyut di halaman panjang) baru kelihatan setelah halamannya cukup
  panjang untuk digulir.
- **`progress.delay` harus tetap `0` di `resources/js/app.ts`.** Inertia
  memasang `setTimeout(start, delay)` saat kunjungan mulai lalu membatalkannya
  di `inertia:finish`; kalau bar-nya belum sempat mulai, `finish()` keluar tanpa
  merender apa pun. Dengan nilai bawaan 250 ms dan navigasi backoffice yang
  selesai dalam 14–21 ms, bar-nya **tidak pernah muncul sekali pun** — dan itu
  terbaca sebagai "warnanya salah", bukan sebagai "tundaannya kepanjangan".
  Tingginya (3px) diatur di `resources/css/app.css` lewat selektor yang diawali
  `html`; CSS Inertia disuntikkan saat runtime, jadi selalu datang setelah
  stylesheet kita dan menang kalau spesifisitasnya sama.
- **Skeleton punya durasi MINIMAL, dan itu satu-satunya sebab ia terlihat.**
  `MIN_SKELETON_MS` (420) di `useIndexFilters.ts`. Tanpa itu ia menyala dan
  padam dalam 14–21 ms — secara teknis benar, dan tidak pernah tergambar sekali
  pun. Penyakitnya sama persis dengan `progress.delay` di bawah: yang terbaca
  bukan "terlalu cepat", melainkan "skeletonnya tidak jalan".
- **Skeleton juga menyala saat halaman ITU SENDIRI baru tiba**, bukan cuma saat
  filter dan pindah halaman. `loading` di `useIndexFilters` dimulai dari `true`
  dan dipadamkan `onMounted`, dihitung dari `sinceNavigationStart()` di
  `resources/js/navigationClock.ts` — jam itu hidup di luar komponen karena
  halaman Inertia baru dipasang SETELAH propsnya tiba, jadi dari dalamnya tidak
  ada cara tahu barusan ada yang menunggu. Kalau `installNavigationClock()` di
  `app.ts` dihapus, jamnya berhenti di waktu muat pertama dan tiap halaman
  menampilkan rangka 420 ms penuh selamanya.
- **Tiap layar daftar memenuhi empat janji yang sama** — identitas menaut ke
  layar baca, kolom "siapa + kapan", sakelar keadaan yang bekerja tanpa membuka
  formulir, dan ekspor CSV yang mengikuti filter aktif. `StandardListTest` gagal
  kalau modul baru melewatkan salah satunya.
- **`updated_by_id` diisi trait `TracksEditor`, BUKAN controller.** Ia menempel
  di event `saving` model, jadi semua jalur simpan (formulir, sakelar cepat,
  pengurutan) tercatat tanpa ada yang harus ingat. Jangan menugaskannya tangan;
  trait sengaja tidak menimpa nilai yang sudah disetel pemanggil.
- **Ekspor lewat `App\Support\Csv::stream()`.** Di dalamnya BOM UTF-8 (tanpa
  itu Excel Windows membaca judul beraksen sebagai sampah), streaming (bukan
  dikumpulkan di memori), dan nama berkas bertanggal. Query-nya WAJIB memakai
  `filtered()` yang sama dengan layar daftarnya — mengekspor sesuatu yang berbeda
  dari yang sedang dilihat orang adalah cara tercepat membuat angka di rapat
  tidak cocok dengan angka di layar. Pakai `lazy()`, bukan `cursor()`: yang
  kedua membuang eager-load dan mengubah seribu baris jadi tiga ribu query.
- **Ekspor pengguna TIDAK membawa rahasia** — tanpa hash sandi, rahasia TOTP,
  kode pemulihan, maupun token "ingat saya". Berkas CSV berpindah lewat surel
  dan folder bersama, tempat yang tidak punya satu pun perlindungan yang
  dipunyai tabel aslinya. Ada tesnya.
- **PHPUnit 12 hanya membaca ATRIBUT, bukan anotasi.** `@dataProvider` di docblock
  diabaikan diam-diam dan tesnya gagal dengan "Too few arguments"; yang benar
  `#[DataProvider('nama')]`.
- **Pengunggah dan penayang dipisah lewat trait `TracksPublication`.**
  `created_by_id` dan `published_by_id` diisi event model, bukan controller —
  alasan yang sama dengan `TracksEditor`: penayangan punya tiga jalur (tombol
  Publish, pemilih Visibility di daftar, penyuntingan jadwal). Penayang dicatat
  **sekali**, saat pertama kali benar-benar tayang; menimpanya tiap kali status
  disentuh membuat kolom Published menyebut orang yang cuma menyunting judulnya
  tiga bulan kemudian. Dipakai `GalleryItem` dan `Document`; ada tesnya di
  keduanya.
- **Urutan FAQ milik HALAMAN, bukan milik FAQ.** `faq_placements` menyimpan
  `(faq_id, page, position)`; satu pertanyaan yang menempel di Domino DAN
  Tournament punya dua peringkat yang berdiri sendiri. Kolom JSON `faqs.pages`
  sudah DIBUANG — dua sumber untuk "halaman mana" persis bug yang diperbaiki.
  `faqs.position` masih ada tapi artinya menyempit: urutan di halaman FAQ
  LENGKAP (`/page/faq`), bukan di Home/Domino/Tournament. Karena itu ada dua
  layar urutan, dan keduanya mengatakan yang mana yang mereka atur —
  `/faq/manage` untuk yang global, `/faq/pages` untuk per halaman. Jangan
  menomori ulang lewat `HasPosition::applyOrder()` untuk penempatan: ia
  menyentuh seluruh tabel, dan itu justru cara urutan Home ikut bergeser saat
  Domino diurutkan. Pakai `FaqPlacement::applyOrderOn($page, $ids)`.
- **Formulir FAQ tidak lagi bisa memindahkan pertanyaan antar halaman.**
  "Apply to Page" di sana sekarang BACA-SAJA; `FaqRequest` bahkan tidak
  mengenal field `pages`, jadi mengirimnya tangan pun tidak melakukan apa-apa
  (ada tesnya). Satu-satunya pintu adalah `/faq/pages`. Dan di layar itu
  pertanyaan NONAKTIF tetap memakai slotnya — membalik aturan lama; halamannya
  digambar utuh sekarang, dan daftar lima baris dengan penghitung "3 dari 3"
  adalah layar yang membantah dirinya sendiri.
- **Empat modul memakai kosakata status yang SAMA** — News, Gallery, Documents,
  dan (lewat `VisibilitySelect`) apa pun sesudahnya: `draft`, `scheduled`,
  `published`, `unpublished`, plus `visibility` yang menurunkannya jadi kunci
  `StatusPill`. Jangan menambahkan sakelar boolean `is_active` untuk pertanyaan
  yang sama; Documents baru saja naik dari sana karena dua keadaan tidak bisa
  menjawab empat.
- **`Tournament::stage` dan `registration_state` DITURUNKAN, bukan kolom.**
  Keduanya sepenuhnya ditentukan tanggal yang sudah ada di baris itu, jadi
  menyimpannya berarti kolom yang basi setiap tengah malam kecuali ada cron yang
  menyegarkannya — dan cron yang mati tidak memberi tahu siapa pun. Penyaring
  `stage` di daftar karena itu menyaring lewat TANGGAL, bukan `where('stage')`.
  Jangan tertukar dengan `visibility`: yang itu menjawab "apakah halamannya
  tayang", yang ini "apakah pertandingannya sedang berlangsung" — turnamen bisa
  `published` DAN `completed` sekaligus.
- **Kelompok berulang di Add Tournament DITULIS ULANG tiap simpan**, bukan
  dicocokkan baris per baris. Ofisial dan jadwal bisa ditambah, dihapus, DAN
  diurutkan ulang; mencocokkan yang lama dengan yang baru menuntut id di
  formulir, dan id itu tidak berarti apa-apa bagi orang yang menyeret baris.
  Foto ofisial tetap bertahan lewat **id barisnya** yang dikirim balik —
  BUKAN `photo_path`. Path dari klien adalah jalan masuk untuk menunjuk berkas
  mana pun di disk, dan ada tesnya.
- **Modul dokumen bernama `Document`, BUKAN `PressRelease`.** Press Releases
  cuma salah satu nilai kolom `category` (`478:5386`). Kalau menemukan nama lama
  di mana pun, itu sisa yang terlewat — renamenya tuntas 2026-09-02.
- **Mengganti nama izin: RENAME barisnya, jangan buat ulang.**
  `role_has_permissions` menunjuk `permissions.id`, bukan namanya. Membuat baris
  baru lewat `AccessSeeder` berarti tiap peran KUSTOM kehilangan akses ke modul
  itu sampai seseorang mencentangnya lagi satu per satu — tanpa satu pun galat
  yang memberi tahu. Polanya ada di
  `2026_09_02_140000_rename_press_releases_to_documents`.
- **Mengganti nama model berarti memindahkan jejak auditnya juga.**
  `activity_log.log_name` dan `subject_type` merekam nama kelas lama;
  membiarkannya membuat entri lama kehilangan subjeknya dan penyaring modul
  memperlihatkan dua modul untuk satu hal.
- **Endpoint yang MENULIS tinggal di `SubmissionController`, bukan
  `PublicController`.** Bedanya bukan kerapian: yang menulis di-throttle,
  memakai honeypot (`website`), dan membalas 204 tanpa isi. Jangan
  mengembalikan baris yang baru dibuat — itu endpoint publik yang membocorkan
  id berurutan tanpa satu pun alasan. Honeypot-nya belum menangkap apa pun
  sampai situs publik merender fieldnya; ia dipasang lebih dulu supaya sisi
  frontend cukup menambah satu `<input>`.
- **Laporan integritas TIDAK menyimpan identitas apa pun** — tanpa nama, email,
  maupun alamat IP. Halaman yang mengirimnya berjanji kerahasiaan, dan IP
  adalah identitas. Jangan menambahkan kolom "untuk keperluan audit": ada tes
  yang gagal kalau salah satunya muncul, dan layar CMS-nya dibangun di atas
  janji itu (tidak ada tombol balas, tidak ada kolom pengirim).
- **Pendaftaran ulang buletin membalas SUKSES, bukan 422.** Galat di sana
  memberi tahu siapa pun yang mengetik sebuah alamat apakah alamat itu ada di
  daftar. Berlaku juga untuk "Notify me" turnamen.
- **Resource API publik WAJIB memperluas `PublicResource`, dan menulis
  `payload()` — bukan `toArray()`.** Yang kedua sudah dipakai untuk menyaring
  hasilnya: ia membuang `null` (§5.4 — field opsional dihilangkan, bukan dikirim
  `null`) sambil MEMBIARKAN `false`, `0`, dan `''`. `array_filter` tanpa
  callback akan membuang ketiganya juga, dan `0` adalah jawaban sah untuk
  "berapa peserta".
- **Konvensi API publik ada di [docs/API.md](docs/API.md), dan dijaga
  `ApiConventionTest`.** Sembilan aturan yang berlaku di SELURUH endpoint —
  camelCase, array telanjang, `id` string, field opsional dihilangkan, `?limit=`
  yang dijepit, dan satu bentuk galat. Endpoint ke-30 yang ditulis dengan gaya
  sendiri gagal di tes itu, bukan di halaman situs publik yang tiba-tiba kosong.
- **Pesan galat API tidak menyebut isi perut aplikasi.** Bawaan Laravel
  membalas `No query results for model [App\Models\NewsArticle]` — bahkan
  dengan `APP_DEBUG=false`. Penyeragamannya di `bootstrap/app.php`, dibatasi
  `api/*` supaya halaman galat Inertia backoffice tidak ikut jadi JSON.
- **Daftar API mengembalikan array TELANJANG, bukan `{ data: [...] }`.**
  `client.ts` di situs publik membacanya begitu; pembungkus bawaan Laravel akan
  menghasilkan `response.map is not a function` di setiap halaman sekaligus.
  Pakai `Resource::bare()`.
- **Empat field API sudah berupa TEKS SIAP TAMPIL, bukan data mentah** —
  `dateLabel`, `registrationLabel`, `location`, `fileSize`. Halaman yang
  menghitung "in 3 days" dari timestamp akan terus menulis "3 days" sampai
  minggu berikutnya begitu responsnya masuk edge cache. Konsekuensinya:
  **`registrationLabel` tidak boleh di-cache lebih dari sehari.**
- **`config/cors.php` sengaja TIDAK memakai `*`.** Daftarnya dari
  `CORS_ALLOWED_ORIGINS`, dan kosong adalah default yang benar: lupa mengisinya
  menghasilkan galat CORS yang terlihat di konsol, sedangkan wildcard
  menghasilkan lubang yang tidak terlihat di mana pun.
- **`FrontendContentSeeder` adalah satu-satunya sumber data contoh untuk modul
  yang dipakai situs publik.** Isinya disalin dari
  `../landing-page-nuxt/app/lib/api/mock/index.ts` supaya penukaran mock → API
  nanti tidak mengubah tampilan halaman. Jangan menambahkan seeder placeholder
  kedua untuk tabel yang sama — pernah terjadi dengan federasi dan statistik,
  dan hasilnya dua daftar berdampingan yang sama-sama terlihat benar.
  **Berkas gambar tidak ikut**: path mock menunjuk aset di repo situs publik.
- **Ukuran halaman satu angka: `config('dwf.per_page')`.** Jangan menulis
  `paginate(20)` di controller — angka yang berbeda per modul membuat orang
  kehilangan rasa "seberapa jauh saya sudah menggulir" tiap pindah layar.
- **Skeleton dipasang lewat `loading` dari `useIndexFilters`.** Galeri memakai
  bentuk kisi, bukan `SkeletonTable`: bentuk tunggu yang salah menjanjikan tata
  letak yang salah.
- **Toggle di dalam sel tabel pakai `hide-label`.** Labelnya tetap ditulis —
  pembaca layar mendarat langsung di sakelarnya dan tidak punya konteks judul
  kolom maupun nama baris — tapi tidak dicetak. Tanpa itu, tiap baris mencetak
  ulang judulnya di samping sakelar ("Highlight Scheduled: 2026 qualification
  calendar"), dua hal yang sudah terbaca di sel sebelahnya.
- **Tombol yang tidak boleh ditekan digambar MATI, bukan diredupkan.**
  `IconButton` menggambar keadaan nonaktif sebagai lingkaran abu terisi — ikon
  yang pudar di baris tabel terbaca seperti gambar yang gagal dimuat. Dan
  selalu isi `title`-nya dengan ALASAN ia mati; server yang menolak saat
  ditekan memberi tahu terlambat.
- **Jangan menaruh dropdown `absolute` di dalam `DataTable`.** Pembungkusnya
  `overflow-x-auto`, dan itu mengurung sumbu Y juga — menu baris terakhir akan
  terpotong. Pakai `Teleport to="body"` + `fixed`, seperti `RowMenu.vue`.
- **`PhDotsThree` + `weight="fill"` BUKAN tiga titik.** Phosphor menggambarnya
  sebagai kotak membulat terisi dengan titiknya di-knockout. Yang benar
  `PhDotsThreeOutline` + `weight="fill"`.
- **Izin dibangkitkan dari `App\Support\Access`, bukan diketik satu per satu.**
  Menambah modul = menambah satu baris di `MODULES`, lalu `php artisan db:seed
  --class=AccessSeeder`. `super-admin` melewati semuanya lewat `Gate::before`,
  jadi ia TIDAK punya baris izin di database — itu disengaja supaya modul baru
  langsung terjangkau tanpa ada yang harus ingat menjalankan seeder.
- **PostgreSQL TIDAK membuat indeks untuk kolom kunci asing.** MySQL membuatnya
  otomatis, Postgres tidak — ia hanya mengindeks kolom yang DITUNJUK. Jadi tiap
  `foreignId()->constrained()` menghasilkan kolom tanpa indeks, dan akibatnya
  dua-duanya diam: `with()`/`withCount()` memindai tabel penuh, dan
  `ON DELETE CASCADE` memindai tabel anak untuk menemukan yang harus dihapus.
  35 kolom sempat begitu sampai 2026-09-03. Modul baru: tambahkan `->index()`
  di migrasinya.
- **Route TULIS wajib dijaga izin tulis, bukan `.view`.** Sampai 2026-09-03
  delapan modul mendaftarkan seluruh route tulisnya di dalam satu grup
  ber-`can:{modul}.view`, jadi peran `viewer` bisa MENGHAPUS artikel berita —
  izinnya ada di database dan tercentang di layar Roles, yang tidak ada adalah
  yang memeriksanya. Untuk `Route::resource()` pakai `middlewareFor()` per
  aksi; satu `resource()` di dalam grup ber-`.view` mendaftarkan tulis dengan
  izin baca. `WritePermissionTest` menyapu seluruh modul dan gagal kalau yang
  berikutnya lupa.
- **Tiap route modul dijaga `can:`, dan sidebar menyaring dengan izin yang
  sama** (`Navigation::forUser()`). Menu yang tidak boleh dibuka dibuang, bukan
  dinonaktifkan — tombol yang berujung 403 hanya memberi tahu orang tentang
  modul yang bukan urusannya.
- **`User::factory()` menghasilkan pengguna TANPA peran.** Pakai
  `->superAdmin()` atau `->withRole('editor')` secara eksplisit di tes. Kalau
  bawaannya serba boleh, tes yang lupa memikirkan izin akan lolos diam-diam.
- **Tiap perubahan model tercatat lewat trait `RecordsActivity`.** Ia memakai
  `logExcept($this->hidden)`, jadi menambah kolom rahasia berarti menambahnya
  ke `#[Hidden]` — kalau lupa, hash sandi dan rahasia TOTP akan menumpuk di
  jejak audit dalam bentuk yang lebih mudah dipanen daripada tabel aslinya.
  Ada tesnya.
- **Login, logout, percobaan gagal, dan penguncian ikut masuk log** lewat
  `RecordAuthActivity`. Method-nya bernama `on…`, BUKAN `handle…`: Laravel
  memindai `app/Listeners` dan mendaftarkan sendiri tiap method bernama
  `handle*` yang type-hint sebuah event, jadi nama itu membuat keempatnya
  terdaftar dua kali dan setiap login menghasilkan dua baris identik — tanpa
  galat apa pun. Ada tesnya.
- **`Auth::attempt()` tidak dipakai di `LoginRequest`** (2FA menuntut sandi
  diperiksa tanpa membuat sesi), jadi event `Failed` ditembakkan tangan. Kalau
  alur login diubah, pastikan event itu tetap ditembakkan — tanpanya percobaan
  gagal berhenti tercatat, diam-diam.
- **IP dan user agent dicap ke SETIAP entri lewat `Activity::creating`** di
  `AppServiceProvider`, bukan di tiap pemanggil. Catatannya datang dari tiga
  jalur (trait model, listener auth, controller pengaturan) dan menitipkannya
  ke masing-masing berarti suatu saat ada yang lupa.
- **`SiteSetting` sengaja TIDAK memakai trait itu** — kunci primernya string
  sementara `activity_log.subject_id` bigint, dan satu kali Simpan menulis
  sembilan baris. `ContactSettingController` mencatatnya sebagai satu entri.
- **Log aktivitas hanya bisa dibaca.** Jangan menambahkan route hapus atau
  sunting; jejak audit yang bisa dirapikan lewat antarmukanya sendiri berhenti
  jadi jejak audit. Ada tes yang gagal kalau ada route selain GET.
- **Sandi yang benar TIDAK memanggil `Auth::login()` kalau 2FA menyala.**
  `LoginRequest::verifiedUser()` hanya memeriksa kredensial; sesi login baru
  dibuat setelah kode terbukti benar. Jangan menggantinya dengan "login dulu
  lalu dialihkan middleware" — cara itu memberi penyerang yang sudah punya
  sandi sebuah sesi yang sah, dan yang menahannya cuma redirect.
- **`/logout` sengaja DI LUAR grup `auth`.** Pengguna yang berhenti di layar
  2FA belum login, jadi `auth` akan menolaknya dan penanda "menunggu 2FA" di
  sesinya tidak akan pernah terhapus.
- **`migrate:fresh` menghapus pendaftaran 2FA, dan akunnya kembali MENUNTUT
  2FA.** Dua akibat, bukan satu: `two_factor_secret` dan
  `two_factor_confirmed_at` ikut terhapus bersama barisnya, DAN kolom
  `two_factor_enabled` bawaannya `true` sementara `DatabaseSeeder` tidak
  menyetelnya — jadi akun yang tadinya sengaja dimatikan 2FA-nya kembali hidup
  dengan 2FA wajib. Yang terlihat pemakainya: "kenapa saya harus setup 2FA
  lagi?". Jangan jalankan perintah itu di database kerja; `php artisan test`
  sudah memakai `dwf_backoffice_testing` yang terpisah.
- **Rahasia TOTP dibuat sekali lalu ditahan di sesi sampai dikonfirmasi.**
  Membuatnya ulang tiap kali halaman setup dimuat berarti orang yang sudah
  memindai QR lalu menyegarkan halaman tidak akan pernah bisa memasukkan kode
  yang benar. Ada tesnya.
- **QR digambar sendiri sebagai SVG (`bacon/bacon-qr-code`).** Jangan pernah
  memakai Google Chart API seperti yang banyak beredar — URL-nya memuat rahasia
  TOTP utuh di query string, jadi kunci 2FA tiap pengguna ikut tercatat di log
  akses pihak ketiga, riwayat browser, dan header Referer.
- **reCAPTCHA aktif hanya kalau `RECAPTCHA_SITE_KEY` dan `RECAPTCHA_SECRET_KEY`
  keduanya terisi.** Tanpa itu ia transparan: aturan validasinya jadi
  `nullable`, komponennya tidak dipasang, dan skrip Google tidak pernah dimuat.
  Jangan menambahkan flag `enabled` terpisah — sakelar yang bisa menyala
  sementara kuncinya kosong berarti login yang menolak semua orang.
  **Verifikasinya sengaja gagal-terbuka** saat Google tidak bisa dihubungi;
  alasannya di `app/Support/Security/Recaptcha.php`, dan ada tes yang mengunci
  perilaku itu supaya perubahannya tidak terjadi diam-diam.
- **Akun admin dibuat TANPA sandi.** Formulir tambah menolak field `password`
  (`prohibited`), dan yang menggantikannya undangan sekali pakai 72 jam. Sandi
  `null` bukan celah: `Hash::check` terhadapnya selalu gagal DAN `LoginRequest`
  menolaknya lebih dulu, dan ada tes untuk keduanya. Layar SUNTING tetap punya
  field sandi opsional — tanpa itu, akun yang undangannya hangus tidak punya
  jalan pemulihan selain menerbitkan undangan baru.
- **Yang disimpan hash token undangan, bukan tokennya.** `AdminInvitation::issue()`
  mengembalikan token mentah SEKALI, untuk dirangkai jadi tautan; kalau
  pemanggil membuangnya, satu-satunya jalan menerbitkan yang baru. Karena itu
  "kirim ulang" selalu menerbitkan token baru dan mencabut yang lama — dua
  tautan hidup sekaligus berarti "Revoke" cuma mematikan salah satunya.
- **Surel undangan dikirim DI LUAR transaksi.** Di dalamnya, SMTP yang gagal
  akan me-rollback akunnya dan super admin melihat "gagal" untuk akun yang di
  sebagian jalur sudah telanjur ada. Kegagalan kirim juga tidak dilempar ke
  atas: yang muncul pesan bahwa tautannya perlu dikirim ulang, dan tombolnya
  sudah ada.
- **`last_login_at` diisi listener event `Login`, BUKAN controller.** Login
  terjadi lewat tiga pintu — formulir, layar 2FA, dan penerimaan undangan — dan
  ketiganya menembakkan event yang sama. Pakai `updateQuietly()`: kolom ini
  berubah tiap orang masuk, dan mencatatnya sebagai perubahan model akan
  menenggelamkan jejak audit.
- **Kolom "Result" di Audit Log diturunkan dari nama kejadian**, bukan kolom
  database. Daftarnya `ActivityLogController::BLOCKED_EVENTS` (`failed`,
  `lockout`, `access_denied`) dan dipakai DUA kali — untuk mencetak kolomnya dan
  untuk menyaringnya — supaya penyaring dan kolom tidak bisa berbeda pendapat.
  Kejadian penolakan yang baru harus ditambahkan ke sana.
- **Model peran DIPERLUAS jadi `App\Models\Role`** dan didaftarkan di
  `config/permission.php`. Tanpa baris config itu, `HasRoles` tetap membuat
  instance milik paket dan relasi `editor()` tidak pernah ada — tanpa satu pun
  galat. Empat kolom tambahannya (`type`, `scope`, `summary`, `updated_by_id`)
  dituntut layar `528:9745`; `type = system` hanya diberikan `AccessSeeder`.
- **Daftar IP DITEGAKKAN, dan cara ia "mati" adalah dengan kosong.** Tidak ada
  flag `enabled` — alasannya sama dengan reCAPTCHA di atas. `EnforceIpWhitelist`
  meloloskan siapa pun yang **tidak disasar** aturan mana pun, jadi tabel kosong
  berarti tidak ada yang dibatasi. Membaliknya ("belum ada aturan, tolak semua")
  akan mengunci seluruh admin pada migrasi pertama, sebelum ada satu orang pun
  yang bisa masuk untuk membuat aturannya.
- **Jangan menambahkan pengecualian super-admin di daftar IP.** Ia tidak
  dikecualikan, dan itu disengaja — akun paling berkuasa justru yang paling
  perlu dibatasi asalnya. Yang menahan orang mengusir dirinya sendiri adalah
  `IpWhitelist::wouldLockOut()`, dipanggil di store, update, sakelar status, DAN
  hapus. Empat jalur; melewatkan satu saja cukup untuk mengunci backoffice lewat
  jalan yang tidak dijaga.
- **Kedaluwarsa aturan IP dihitung saat request** lewat scope `enforceable()`,
  bukan job terjadwal yang mematikan `is_active`. Dengan scheduler, jendela
  antara "kedaluwarsa" dan "job berikutnya jalan" adalah akses yang seharusnya
  sudah dicabut — panjangnya tergantung cron yang tidak kelihatan dari layar
  mana pun. Karena itu Validity dan Status memang dua kolom berbeda: sebuah
  aturan bisa `is_active` DAN sudah lewat tanggalnya sekaligus.
- **Seeder daftar IP menanam baris NONAKTIF.** Aturan "All Admins" yang aktif
  menegakkan dirinya pada request berikutnya, jadi `db:seed` di mesin yang bukan
  `local` akan mengunci pemakainya dengan data contoh — dan layar yang bisa
  membatalkannya ada di balik pintu yang baru saja terkunci.
- **Bahasa bawaan Inggris, dan pengalihnya disembunyikan.** Sakelarnya
  `DWF_LOCALE_SWITCHER` (bawaan `false`): saat mati, tombolnya hilang dari
  topbar, `/locale` membalas 404, dan `users.locale` yang tersimpan diabaikan.
  Yang terakhir itu wajib — tanpa mengabaikannya, mematikan pengalih akan
  mengunci siapa pun yang pernah memilih bahasa lain, karena preferensinya
  tetap terbaca sementara tombol untuk mengubahnya sudah tidak ada. Seluruh
  mesin terjemahannya tetap utuh; menyalakan kembali cukup satu baris `.env`.
- **Semua teks antarmuka lewat `lang/{en,id}/backoffice.php`.** Jangan menulis
  kalimat langsung di `.vue` atau di controller — pakai `t('grup.kunci')` di Vue
  dan `__('backoffice.grup.kunci')` di PHP. Bahasa Inggris adalah SUMBERNYA
  (label wireframe ditulis dalam Inggris); `lang/id` menerjemahkannya.
  Menambah string berarti menambahnya di **kedua** berkas — `LocalizationTest`
  membandingkan struktur kuncinya dan gagal kalau ada yang ketinggalan.
  Kunci yang hilang **tidak** melempar galat: `t()` mengembalikan kuncinya apa
  adanya, jadi yang muncul di layar adalah teks seperti `news.field_title`.
- **Jangan menulis assertion tes yang bergantung pada kalimat terjemahan.**
  Bawaannya Indonesia, jadi mencocokkan teks Inggris akan gagal — dan tesnya
  akan gagal lagi tiap kali kalimatnya diperhalus. Periksa `assertSessionHasErrors`,
  kode status, atau bagian yang sama di semua bahasa.
- **Status dikirim sebagai KUNCI, bukan teks jadi.** `NewsArticle::visibility`
  mengembalikan `draft`/`scheduled`/`posted`; `StatusPill` yang menerjemahkannya.
  Mengirim "Draft" dari PHP berarti kolom itu tetap Inggris walau bahasanya
  diganti.
- **Deskripsi blok halaman hukum HTML dasar — profil Purifier `legal`, dan
  toolbarnya HARUS cocok dengan daftar itu.** `RichTextEditor variant="basic"`
  menggambar tebal/miring/garis bawah/coret/daftar dan tidak lebih; profil
  `legal` di `config/purifier.php` mengizinkan tepat tag yang sama, plus `<a>`
  — StarterKit 3 memuat ekstensi Link, jadi tautan lahir dari TEMPELAN meski
  tidak ada tombolnya, dan membuangnya berarti tautan yang hilang diam-diam
  dari teks yang disalin orang dari dokumen lain. Tombol yang
  ada di satu sisi tapi tidak di sisi lain akan menghapus pekerjaan penulisnya
  saat disimpan, tanpa satu pun pesan — itu sebabnya judul, gambar, dan sorot
  dimatikan di KEDUANYA. (Membalik keputusan lama yang memakai `strip_tags()`;
  alasan lama — textarea polos yang menampilkan `<p>` sisipan `AutoParagraph`
  sebagai teks terketik — hilang begitu kotaknya bukan textarea lagi.) Ada
  tesnya, dua: satu mengunci yang lolos, satu mengunci yang dibuang.
- **Formulir yang isinya DITULIS ULANG server harus disemai ulang dari props
  setelah sukses** (`form.defaults(seed()); form.reset()`), seperti di
  `Legal/Form.vue`. Tanpa itu kotaknya memperlihatkan apa yang diketik, bukan
  apa yang tersimpan — dan selisihnya baru ketahuan pada muat ulang penuh
  berikutnya, saat orangnya sudah telanjur yakin.
- **Perubahan yang hanya menyentuh tabel ANAK tidak menggerakkan `updated_at`
  induknya.** Eloquent melewati baris yang tidak kotor, jadi menyunting blok
  tanpa menyentuh slug membuat "Last Modified" membeku. `LegalPageController`
  memanggil `$page->touch()` di dalam transaksinya; ada tes yang gagal kalau itu
  dihapus.
- **`config/purifier.php` DIPUBLIKASIKAN, dan daftar `HTML.Allowed`-nya sudah
  diperbaiki.** Bawaan `mews/purifier` tidak memuat `h2`, `h3`, `s`, maupun
  `mark` — padahal keempatnya tombol yang ada di toolbar editor. Yang terjadi
  sebelum ini: orang menekan "Heading 2", menyimpan, dan judulnya kembali jadi
  paragraf tanpa satu pun pesan galat. Jangan mem-publish ulang config itu dari
  vendor; ada tes yang gagal kalau daftarnya menyempit lagi.
- **Dua disk, dibedakan SIAPA yang boleh membaca — bukan jenis berkasnya.**
  `public` untuk media yang memang tampil (gambar berita, galeri, logo, bendera,
  foto, gambar editor): disajikan web server langsung lewat symlink. `local`
  (`storage/app/private`, TIDAK di-symlink) untuk berkas yang tunduk pada
  sakelar Visibility — sekarang cuma dokumen. Ia keluar HANYA lewat
  `MediaController`, yang memeriksa status barisnya tiap permintaan. Nama
  berkas acak bukan kontrol akses: ia menahan tebakan, bukan tautan yang sudah
  beredar. Modul baru yang berkasnya punya Visibility harus ikut `local`, dan
  `StoredFile::put(..., disk: 'local')` yang mengarahkannya.
- **Gambar publik akan disajikan dari DOMAIN SENDIRI di produksi**
  (`MEDIA_URL`, mis. `media.dwf-domino.org`) — origin terpisah, supaya berkas
  unggahan tidak pernah berjalan di origin aplikasi. Dokumen TIDAK ikut: ia
  tunduk pada sakelar Visibility dan keluar lewat `MediaController`, jadi situs
  publik memang punya dua asal berkas. Konsekuensi yang mudah terlewat ada di
  repo sebelah: `@nuxt/image` sekarang `provider: "none"` sehingga host media
  mana pun bekerja, tapi begitu IPX dinyalakan lagi host itu WAJIB masuk
  `image.domains` — kalau tidak, setiap gambar CMS 403 sekaligus sementara aset
  bawaan tetap tampil, yang terbaca seperti backend yang rusak.
- **Nama berkas unggahan acak, dan penggantian SELALU menulis nama baru.**
  `StoredFile::put()` memakai `hashName()` (40 karakter) lalu membuang path
  lama. Konsekuensinya bukan cuma "tidak bisa ditebak": satu URL selalu berisi
  hal yang sama SELAMANYA, jadi gambar publik boleh di-cache `immutable` setahun
  penuh. Jangan pernah menggantinya jadi nama berbasis slug — slug berubah saat
  judul disunting, dan cache setahun akan menyajikan gambar lama sampai TTL
  habis. Dokumen dikecualikan (`no-store`, ada tesnya): berkas ter-cache tetap
  terunduh setelah dokumennya diturunkan.
- **Batas gambar 1 MB, bukan 2 MB seperti tertulis di wireframe.** Situs publik
  memakai `provider: "none"` di `@nuxt/image`, jadi tidak ada yang mengecilkan
  apa pun: byte yang diunggah adalah byte yang dikirim ke setiap pengunjung.
  Angkanya diukur, bukan ditebak — catatannya di `config/dwf.php`. Naikkan lagi
  hanya setelah IPX benar-benar hidup.
- **Logo partner STATIS di situs publik; menunya sudah dibuang dari sidebar.**
  Layar `/blocks`, tabel `partners`, dan `/api/v1/partners` masih ada dan masih
  bekerja — cuma tidak ditaut. Mengembalikannya satu baris di `Navigation`.
  Jangan menambahkan lagi baris partner ke kartu "Dikelola di tempat lain" atau
  ke widget Dashboard: keduanya akan menjanjikan bahwa menyunting di sana
  mengubah halaman.
- **`php artisan dwf:demo-images` yang mengisi gambar contoh, bukan seeder.**
  Seeder tidak pernah menanam berkas biner. Perintah itu menolak jalan di
  produksi, dan gambarnya berlabel "CONTOH" supaya tidak ada yang membiarkannya
  tayang.
- **Gambar WAJIB WebP, di semua modul** (`dwf.uploads.image_mimes`). `mimes:webp`
  membaca mime asli lewat fileinfo, jadi `.png` yang diganti namanya tetap
  ditolak. `accept="image/webp"` di komponennya cuma menyaring dialog berkas —
  ia bisa dilewati dengan memilih "Semua berkas", jadi jangan pernah
  memperlakukannya sebagai penjagaan.
- **Ukuran gambar per slot ada di `dwf.uploads.image_specs`,** bukan diketik di
  aturan validasi. Angkanya muncul di tiga tempat (aturan, kalimat galat, hint
  di bawah label) dan tiga salinan pasti berpisah. Yang dijaga `min_width` +
  `min_height` + `ratio`, BUKAN ukuran persis: 3840×1600 mengisi kotak hero sama
  baiknya dengan 1920×800 dan lebih tajam di layar retina.
- **Gambar di dalam editor diunggah ke `/editor/images`, bukan disisipkan sebagai
  base64.** Ekstensi tiptap dipasang dengan `allowBase64: false` — gambar 1 MB
  sebagai data URI jadi ~1,4 MB teks di kolom `body`, ikut terkirim tiap muat
  halaman, dan tidak bisa di-cache browser mana pun. Endpoint-nya dijaga gate
  `upload-editor-image` (punya izin `*.create` atau `*.update` di modul mana
  pun), bukan `auth` saja: `viewer` bisa masuk dan hanya boleh membaca.
- **Menambah kolom editor berarti menambahnya di TIGA tempat.** Sanitasi
  (`Purifier::clean()` di controller), tesnya, DAN
  `PruneEditorImages::HTML_COLUMNS` — kalau yang ketiga terlewat, penyapu
  mingguan akan menghapus gambar yang masih dipakai, dan itu satu-satunya
  kegagalan di repo ini yang tidak bisa dibatalkan. `overview` turnamen sempat
  luput dari ketiganya sekaligus sampai 2026-09-03; `PruneEditorImagesTest`
  sekarang gagal kalau jumlah kolom yang dibersihkan tidak sama dengan jumlah
  kolom yang bisa disapu.
- **Penyapu gambar editor punya AMBANG USIA, dan itu yang membuatnya aman.**
  Gambar diunggah saat DISISIPKAN, bukan saat formulirnya disimpan — jadi ada
  jendela nyata di mana berkas sudah ada di disk tapi belum disebut baris mana
  pun: selama orangnya masih mengetik. `--days=7` bawaannya; menurunkannya ke 0
  berarti menghapus gambar dari bawah formulir yang sedang dibuka.
- **Dua jadwal butuh SATU entri cron di server.**
  `* * * * * php artisan schedule:run` — tanpa itu `editor:prune` dan
  `activitylog:clean` tidak pernah jalan, dan tidak ada satu pun layar yang
  memperlihatkannya. Keduanya membereskan hal yang tumbuh tanpa batas.
- **`<meta name="csrf-token">` di `app.blade.php` dipakai unggahan editor.**
  Inertia tidak membutuhkannya (axios membaca cookie `XSRF-TOKEN` sendiri), tapi
  `fetch` tidak melakukan itu — tanpa baris itu yang muncul 419 tanpa penjelasan.
- **Setiap HTML dari editor WAJIB lewat `Purifier::clean()` sebelum disimpan.**
  Editor tiptap adalah kenyamanan mengetik, bukan batas keamanan — HTML-nya
  nanti tampil di situs publik. Ada tes untuk News, FAQ, dan Legal Pages yang
  gagal kalau `Purifier` dilewati. Isi pesan kontak **tidak** dibersihkan tapi
  juga tidak pernah dirender sebagai HTML: ia dicetak dengan `{{ }}` biasa.
- **Route yang lebih spesifik didaftarkan lebih dulu.** `/news/categories` harus
  ada SEBELUM `/news/{article}`, kalau tidak "categories" dibaca sebagai id
  artikel dan berakhir 404. Sudah ada tes untuk ini.
- **Kunci nullable yang tidak dikirim TIDAK muncul di data tervalidasi.**
  `$data['alt']` melempar "Undefined array key", bukan mengembalikan null. Baca
  semua field opsional dengan `??`.
- **`PUT` tidak membawa berkas.** Untuk form dengan unggahan, kirim `POST`
  dengan `_method: 'put'` lewat `form.transform(...)` — pola ini dipakai di
  News, Press Releases, dan Gallery.
- **Warna grafik bukan warna merek.** Emas `#E1B762` dan navy `#001D6C` gagal
  pemeriksaan pita terang dan kontras sebagai warna seri. Token `--color-series-*`
  di `app.css` sudah divalidasi untuk kedua mode — jangan menukarnya tanpa
  menjalankan ulang validatornya.
- **Ikon lewat Phosphor (`@phosphor-icons/vue`).** Untuk sidebar, tambahkan
  namanya ke peta di `resources/js/Components/Sidebar/NavIcon.vue` — peta
  eksplisit, bukan impor dinamis, supaya bundel tidak menarik 9.000 ikon.
- **Tes jalan di PostgreSQL** (`dwf_backoffice_testing`), bukan sqlite memori.
  Kalau `php artisan test` mengeluh soal koneksi, database itu belum dibuat:
  `createdb dwf_backoffice_testing`.
- **`vue-tsc` butuh TypeScript 5.** TypeScript 7 (port native) menghapus
  subpath `./lib/tsc` yang dipakai `vue-tsc`, dan `bun run typecheck` mati
  dengan `ERR_PACKAGE_PATH_NOT_EXPORTED`. `typescript` sengaja dipin ke `^5`.
- **Database produksi dipasang dengan `php artisan dwf:install`, BUKAN
  `db:seed`.** Yang kedua memang membuat akun admin — dan sekaligus menanam
  berita contoh, turnamen, dokumen, pesan kontak, dan aturan daftar IP. Di
  produksi itu bukan awal yang bersih melainkan pekerjaan menghapus.
  `dwf:install` mengerjakan tiga hal yang wajib ada sebelum satu orang pun bisa
  masuk (izin, super admin pertama, baris SEO bawaan `*`), aman diulang, dan
  **tidak pernah menimpa sandi admin yang sudah ada** — sandi di `.env` server
  bisa jauh lebih tua daripada yang dipakai orangnya. Ada tesnya.
- **Kredensial admin tidak pernah ditulis di kode.** Seeder membacanya dari
  `DWF_ADMIN_EMAIL` / `DWF_ADMIN_PASSWORD` dan menolak jalan kalau kosong.

## Gerak (motion-v)

- **Paket animasinya `motion-v`**, port Vue resmi dari organisasi Motion —
  bukan `motion` dan bukan `framer-motion`. Nama propnya beda dari React:
  `whilePress` (bukan `whileTap`), `inViewOptions` (bukan `viewport`).
- **Durasi dan pegas TIDAK ditulis di komponen.** Semuanya dari
  `resources/js/motion.ts` (`FADE`, `RISE`, `SPRING_SNAP`, `SPRING_SOFT`,
  `rowDelay()`) — alasannya sama dengan token warna.
- **`prefers-reduced-motion` sudah ditangani SEKALI** lewat
  `<MotionConfig reduced-motion="user">` di `app.ts`. Jangan memeriksanya lagi
  per komponen. Yang TIDAK ikut tertangani cuma animasi CSS murni: `AppButton`
  dan `scroll-behavior` punya `motion-reduce:` / media query-nya sendiri.
- **`AnimatePresence` merender Fragment, bukan elemen** (ia `TransitionGroup`
  Vue tanpa `tag`). Itu yang membuatnya sah dipakai langsung di dalam
  `<tbody>`. Tiap anaknya WAJIB punya `key`.
- **`ref` pada komponen `motion.*` memberi INSTANCE-nya, bukan elemennya.**
  `panel.offsetHeight` akan `undefined` tanpa suara. Pakai fungsi-ref yang
  membuka `$el` — polanya ada di `RowMenu.vue` (`bindPanel`).
- **`AppButton` sengaja memakai transisi CSS, bukan `motion-v`.** Ia polimorfik
  (`<button>` / `<a>` / `<Link>`) dan menanggung semua tombol submit; alasan
  lengkapnya ada di komennya sendiri. `IconButton` yang pakai pegas.

## Modal dan penjaga formulir

- **`<dialog>` TIDAK di tengah kalau hanya diberi `showModal()`.** Preflight
  Tailwind menyetel `margin: 0` ke semua elemen dan itu menghapus `margin: auto`
  dari stylesheet browser — modalnya menempel di kiri atas. `ConfirmDialog`
  merentangkan dialognya selayar penuh, membuatnya transparan, lalu menengahkan
  isinya dengan flex; scrim-nya digambar sendiri karena `::backdrop` pseudo-
  element dan Motion tidak bisa menganimasikannya.
- **Dialog "Reload site?" saat mengetik di formulir adalah VITE, bukan bug.**
  `refresh: true` di `vite.config.js` memantau `lang/**`, `routes/**`, dan
  `resources/views/**` (bawaan `laravel-vite-plugin`). Menyimpan salah satunya
  memanggil `location.reload()` di browser yang sedang terbuka, dan kalau ada
  formulir yang sudah diketik, `beforeunload` menyala. `UnsavedGuard` melucuti
  dirinya untuk satu muat ulang itu lewat `import.meta.hot.on('vite:beforeFullReload')`.
  Perhatikan bedanya saat menyelidiki: browser menulis **"Reload site?"** untuk
  `location.reload()` dan **"Leave site?"** untuk navigasi — kata pertama
  berarti sesuatu memuat ulang halamannya, bukan orangnya yang pergi.
- **Tautan `#fragmen` di halaman formulir memicu penjaga "belum disimpan".**
  Browser menghitung navigasi fragmen sebagai perpindahan entri riwayat, jadi
  `popstate` menyala dan `leaveGuard` membacanya sebagai kepergian — padahal
  halamannya tidak ke mana-mana. Dua lapis menahannya sekarang: `leaveGuard`
  melewatkan perpindahan yang origin, pathname, dan search-nya sama (`sameDocument()`),
  dan `ProgressSteps` menggulir dengan `scrollIntoView` tanpa menyentuh riwayat
  sama sekali. Yang kedua juga menyelamatkan tombol Back: delapan langkah
  berarti delapan entri hash yang harus disusuri sebelum orang benar-benar keluar.
- **`installLeaveGuard()` di `app.ts` HARUS sebelum `createInertiaApp()`.**
  Inertia memasang pendengar `popstate`-nya di dalam `router.init()` dan
  langsung menukar komponen halaman; satu-satunya cara menahannya adalah
  `stopImmediatePropagation()`, yang cuma menghentikan pendengar yang terdaftar
  sesudahnya. Dipindah satu baris ke bawah: masih ter-compile, masih lolos tes,
  tidak pernah menahan apa pun.
- **Penjaga hanya menahan kunjungan GET.** Pengiriman formulir selalu
  POST/PUT/PATCH/DELETE (termasuk unggahan, yang POST dengan `_method`). Tanpa
  saringan itu, tombol Save-lah yang pertama kena tahan.
- **Halaman formulir baru wajib memasang `<UnsavedGuard :dirty="form.isDirty" />`.**
  Tombol Cancel tidak perlu penanganan khusus — ia sudah `<Link>` Inertia, jadi
  lewat pintu yang sama. Yang "formulir" bukan cuma yang punya `useForm`:
  Manage Category (sunting inline) dan Manage Order FAQ (urutan seret) juga
  memasangnya dengan penanda `dirty` buatan sendiri.
- **`dirty` harus berarti "ADA yang hilang", bukan "sedang menyunting".**
  Di Manage Category, menekan pensil saja tidak menyalakannya — hanya kalau
  namanya benar-benar berbeda dari yang tersimpan. Dialog yang muncul untuk
  perubahan nol cuma mengajari orang menutupnya tanpa membaca, dan sesudah itu
  ia akan menutup yang sungguhan dengan cara yang sama.

## Tata letak formulir

- **`AppField` memakai `inheritAttrs: false`; atribut lepas mendarat di
  kontrolnya, bukan di `<div>` pembungkusnya.** Bawaannya sebaliknya, dan itu
  gagal tanpa suara: `aria-label` di sebuah div tidak dibacakan siapa pun dan
  `autofocus` di elemen yang tidak bisa difokuskan tidak melakukan apa-apa.
  Keduanya sempat terpakai berbulan-bulan sebelum ketahuan.
- **Kolom label `FormRow` selalu 280px.** Prop `compact` HANYA memengaruhi
  kolom kanan. Dulu ia ikut melepas lebar labelnya, dan akibatnya baris toggle
  dan baris field berdiri di garis yang berbeda — satu kartu terlihat seperti
  dua tabel yang ditumpuk.
- **Menu sidebar menyala lewat awalan TERPANJANG yang cocok**, bukan `===`.
  Dengan perbandingan persis, tiap layar anak memadamkan menunya sendiri:
  `/news` menyala, `/news/create` kosong.

## Alur kerja

Perbarui [docs/PROGRESS.md](docs/PROGRESS.md) setiap menyelesaikan modul, dan
catat penyimpangan dari wireframe di sana — bukan di pesan commit.
